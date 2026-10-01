#include <assert.h>
#include <string.h>
#include "esp_event.h"
#include "esp_heap_caps.h"
#include "esp_http_client.h"
#include "esp_log.h"
#include "esp_netif.h"
#include "esp_timer.h"
#include "esp_wifi.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "driver/gpio.h"
#include "driver/i2s_std.h"
#include "nvs_flash.h"

static const char *TAG = "ark_voice";

#define SAMPLE_RATE 16000
#define MAX_SECONDS 20
#define BYTES_PER_SAMPLE 2
#define MAX_PCM_BYTES (SAMPLE_RATE * BYTES_PER_SAMPLE * MAX_SECONDS)

static i2s_chan_handle_t rx_chan;
static uint8_t *pcm_buf;

static void wifi_init(void)
{
    ESP_ERROR_CHECK(esp_netif_init());
    ESP_ERROR_CHECK(esp_event_loop_create_default());
    esp_netif_create_default_wifi_sta();
    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    ESP_ERROR_CHECK(esp_wifi_init(&cfg));
    wifi_config_t wifi_config = {0};
    strncpy((char *)wifi_config.sta.ssid, CONFIG_ARK_WIFI_SSID, sizeof(wifi_config.sta.ssid) - 1);
    strncpy((char *)wifi_config.sta.password, CONFIG_ARK_WIFI_PASSWORD, sizeof(wifi_config.sta.password) - 1);
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_STA, &wifi_config));
    ESP_ERROR_CHECK(esp_wifi_start());
    ESP_ERROR_CHECK(esp_wifi_connect());
}

static void i2s_init(void)
{
    i2s_chan_config_t chan_cfg = I2S_CHANNEL_DEFAULT_CONFIG(I2S_NUM_0, I2S_ROLE_MASTER);
    ESP_ERROR_CHECK(i2s_new_channel(&chan_cfg, NULL, &rx_chan));
    i2s_std_config_t std_cfg = {
        .clk_cfg = I2S_STD_CLK_DEFAULT_CONFIG(SAMPLE_RATE),
        .slot_cfg = I2S_STD_PHILIPS_SLOT_DEFAULT_CONFIG(I2S_DATA_BIT_WIDTH_32BIT, I2S_SLOT_MODE_MONO),
        .gpio_cfg = {
            .mclk = I2S_GPIO_UNUSED,
            .bclk = CONFIG_ARK_I2S_BCLK_GPIO,
            .ws = CONFIG_ARK_I2S_WS_GPIO,
            .dout = I2S_GPIO_UNUSED,
            .din = CONFIG_ARK_I2S_SD_GPIO,
            .invert_flags = {0},
        },
    };
    ESP_ERROR_CHECK(i2s_channel_init_std_mode(rx_chan, &std_cfg));
}

static void write_wav_header(uint8_t *out, uint32_t pcm_bytes)
{
    uint32_t data_chunk = pcm_bytes;
    uint32_t riff_size = 36 + data_chunk;
    uint32_t byte_rate = SAMPLE_RATE * BYTES_PER_SAMPLE;
    memcpy(out, "RIFF", 4);
    memcpy(out + 4, &riff_size, 4);
    memcpy(out + 8, "WAVEfmt ", 8);
    uint32_t fmt_size = 16;
    uint16_t audio_format = 1;
    uint16_t channels = 1;
    uint16_t bits = 16;
    uint16_t block_align = 2;
    memcpy(out + 16, &fmt_size, 4);
    memcpy(out + 20, &audio_format, 2);
    memcpy(out + 22, &channels, 2);
    uint32_t sr = SAMPLE_RATE;
    memcpy(out + 24, &sr, 4);
    memcpy(out + 28, &byte_rate, 4);
    memcpy(out + 32, &block_align, 2);
    memcpy(out + 34, &bits, 2);
    memcpy(out + 36, "data", 4);
    memcpy(out + 40, &data_chunk, 4);
}

static void post_wav(const uint8_t *wav, int wav_len)
{
    if (strlen(CONFIG_ARK_LAB_SECRET) == 0) {
        ESP_LOGW(TAG, "No lab secret; skip POST (%d bytes WAV)", wav_len);
        return;
    }

    esp_http_client_config_t config = {
        .url = CONFIG_ARK_LAB_URL,
        .method = HTTP_METHOD_POST,
        .timeout_ms = 30000,
    };
    esp_http_client_handle_t client = esp_http_client_init(&config);
    esp_http_client_set_header(client, "Content-Type", "audio/wav");
    esp_http_client_set_header(client, "X-Voice-Lab-Secret", CONFIG_ARK_LAB_SECRET);
    esp_http_client_set_header(client, "X-Voice-Mic", CONFIG_ARK_MIC_NAME);
    esp_http_client_set_header(client, "X-Voice-Expect", "rr2-lr3");
    esp_http_client_set_post_field(client, (const char *)wav, wav_len);
    esp_err_t err = esp_http_client_perform(client);
    if (err == ESP_OK) {
        ESP_LOGI(TAG, "lab HTTP %d", esp_http_client_get_status_code(client));
    } else {
        ESP_LOGE(TAG, "lab POST failed: %s", esp_err_to_name(err));
    }
    esp_http_client_cleanup(client);
}

static void capture_ptt(void)
{
    gpio_set_level(CONFIG_ARK_LED_GPIO, 1);
    ESP_ERROR_CHECK(i2s_channel_enable(rx_chan));

    int pcm_len = 0;
    uint8_t slot[8];
    size_t n = 0;
    int64_t start = esp_timer_get_time();

    while (gpio_get_level(CONFIG_ARK_PTT_GPIO) == 0) {
        if ((esp_timer_get_time() - start) > (int64_t)MAX_SECONDS * 1000000) {
            ESP_LOGW(TAG, "hit %ds cap", MAX_SECONDS);
            break;
        }
        if (pcm_len + 2 > MAX_PCM_BYTES) {
            break;
        }
        if (i2s_channel_read(rx_chan, slot, sizeof(slot), &n, pdMS_TO_TICKS(50)) != ESP_OK || n < 4) {
            continue;
        }
        /* 32-bit I2S slot → 16-bit PCM (SPH0645 / INMP441 / ICS-43434) */
        int32_t sample = (int32_t)((slot[3] << 24) | (slot[2] << 16) | (slot[1] << 8) | slot[0]);
        int16_t pcm = (int16_t)(sample >> 16);
        memcpy(pcm_buf + 44 + pcm_len, &pcm, 2);
        pcm_len += 2;
    }

    ESP_ERROR_CHECK(i2s_channel_disable(rx_chan));
    gpio_set_level(CONFIG_ARK_LED_GPIO, 0);

    write_wav_header(pcm_buf, (uint32_t)pcm_len);
    ESP_LOGI(TAG, "captured %d PCM bytes", pcm_len);
    post_wav(pcm_buf, 44 + pcm_len);
}

void app_main(void)
{
    ESP_ERROR_CHECK(nvs_flash_init());
    pcm_buf = heap_caps_malloc(44 + MAX_PCM_BYTES, MALLOC_CAP_SPIRAM | MALLOC_CAP_8BIT);
    if (!pcm_buf) {
        pcm_buf = malloc(44 + MAX_PCM_BYTES);
    }
    assert(pcm_buf);

    gpio_config_t ptt = {
        .pin_bit_mask = 1ULL << CONFIG_ARK_PTT_GPIO,
        .mode = GPIO_MODE_INPUT,
        .pull_up_en = GPIO_PULLUP_ENABLE,
        .pull_down_en = GPIO_PULLDOWN_DISABLE,
        .intr_type = GPIO_INTR_DISABLE,
    };
    gpio_config(&ptt);
    gpio_config_t led = {
        .pin_bit_mask = 1ULL << CONFIG_ARK_LED_GPIO,
        .mode = GPIO_MODE_OUTPUT,
        .pull_up_en = GPIO_PULLUP_DISABLE,
        .pull_down_en = GPIO_PULLDOWN_DISABLE,
        .intr_type = GPIO_INTR_DISABLE,
    };
    gpio_config(&led);
    gpio_set_level(CONFIG_ARK_LED_GPIO, 0);

    wifi_init();
    i2s_init();
    ESP_LOGI(TAG, "hold PTT to capture; release to POST lab WAV. mic=%s", CONFIG_ARK_MIC_NAME);

    bool was_down = false;
    while (1) {
        bool down = gpio_get_level(CONFIG_ARK_PTT_GPIO) == 0;
        if (down && !was_down) {
            capture_ptt();
        }
        was_down = down;
        vTaskDelay(pdMS_TO_TICKS(15));
    }
}
