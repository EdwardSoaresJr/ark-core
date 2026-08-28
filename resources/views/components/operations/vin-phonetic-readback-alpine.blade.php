<span class="ops-vin-phonetic-head">
    <span class="ops-vin-phonetic-label">Phone readback</span>
</span>
<span class="ops-vin-phonetic-rows">
    <span class="ops-vin-phonetic-section" x-show="phoneticWmiCells.length">
        <span class="ops-vin-phonetic-row-label">
            WMI
            <span class="ops-vin-phonetic-row-meta">1–3 · manufacturer</span>
        </span>
        <span class="ops-vin-phonetic-row">
            <template x-for="(cell, index) in phoneticWmiCells" :key="'wmi-' + index">
                <span class="ops-vin-phonetic-cell">
                    <span class="ops-vin-phonetic-char" x-text="cell.char"></span>
                    <span class="ops-vin-phonetic-word" x-text="cell.word"></span>
                </span>
            </template>
        </span>
    </span>
    <span class="ops-vin-phonetic-section" x-show="phoneticVdsCells.length">
        <span class="ops-vin-phonetic-row-label">
            VDS
            <span class="ops-vin-phonetic-row-meta">4–9 · descriptor</span>
        </span>
        <span class="ops-vin-phonetic-row">
            <template x-for="(cell, index) in phoneticVdsCells" :key="'vds-' + index">
                <span class="ops-vin-phonetic-cell">
                    <span class="ops-vin-phonetic-char" x-text="cell.char"></span>
                    <span class="ops-vin-phonetic-word" x-text="cell.word"></span>
                </span>
            </template>
        </span>
    </span>
    <span class="ops-vin-phonetic-section ops-vin-phonetic-section--serial" x-show="phoneticVisCells.length">
        <span class="ops-vin-phonetic-row-label ops-vin-phonetic-row-label--serial">
            VIS
            <span class="ops-vin-phonetic-row-meta">10–17 · VIS</span>
        </span>
        <span class="ops-vin-phonetic-row ops-vin-phonetic-row--suffix">
            <template x-for="(cell, index) in phoneticVisCells" :key="'vis-' + index">
                <span class="ops-vin-phonetic-cell ops-vin-phonetic-cell--suffix">
                    <span class="ops-vin-phonetic-char" x-text="cell.char"></span>
                    <span class="ops-vin-phonetic-word" x-text="cell.word"></span>
                </span>
            </template>
        </span>
    </span>
</span>
