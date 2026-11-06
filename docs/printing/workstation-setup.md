# QZ Tray printing: server pair and workstation setup

ARK signs QZ Tray requests with its own certificate pair, not a purchased QZ certificate. Silent
printing needs both halves:

1. **Server**: the shop's signing certificate and its private key, so `POST /app/api/qz/sign-message`
   can sign each request.
2. **Workstation**: QZ Tray on each print computer trusts the root that issued that certificate.

## Server: the shop's signing pair

`App\Ark\Operations\Printing\QzTraySigning` reads the pair from `storage_path('app/private/qz')`:

| File | Contents | Mode |
| --- | --- | --- |
| `digital-certificate.txt` | Signing certificate (leaf, `CA:FALSE`), sent to the browser | 644 |
| `private-key.pem` | Its RSA 2048 key, PKCS#8, unencrypted | 600, owned by the PHP user |
| `override.crt` | The root that issued the leaf (`CA:TRUE`), embedded in ARK Print Setup | 644 |

The folder is 700. A hosted platform that scopes `storage_path()` per shop gives each shop its own
pair. When that folder holds neither the certificate nor the key, `QZ_CERTIFICATE_PATH` and
`QZ_PRIVATE_KEY_PATH` are used (and `QZ_TRUST_ROOT_PATH`, default `override.crt` beside the
certificate).

The pair is ready when the certificate parses, is inside its validity dates, and the key belongs to
it. Check it without printing any key material:

```bash
php artisan ark:printing:qz-check
```

It reports `signing_source` (`shop`, `configured`, `none`), the certificate, its SHA-256 and end date,
`private_key_matches_certificate`, `private_key_mode`, the trust root and `chains_to_trust_root`.
Settings → Label Printing → Certificates & setup shows the same, without server paths.

A shop with no pair gets one with:

```bash
php artisan ark:printing:qz-certificate --generate [--organization="Shop name"]
```

It writes the three files above (root valid 10 years, leaf 5 years), keeps no root key, and refuses
when the shop already has any of the files or signs with configured paths. It never replaces a pair.
To replace one (only when it is unusable or compromised): move the folder aside, generate, and run
ARK Print Setup again on each workstation; the setup replaces ARK's previous root for that shop.

Back up the folder with the shop's storage. Never copy it into a repository, image, or log.

## Workstation: ARK Print Setup

Staff open **Set Up Printing** (`/app/printing/setup`; also linked from print error messages and
Settings → Label Printing). The page offers:

- **Windows**: `ARK-Print-Setup-<host>-<version>.cmd`. Double-click; Windows SmartScreen may ask
  (More info → Run anyway) and UAC asks for administrator approval for the one step that changes files.
- **Mac**: a Terminal command (`curl … && sudo bash …`) with a link valid for 30 minutes, or the
  `.sh` file.

Each file embeds only the shop's public `override.crt` and its SHA-256 and SHA-1 fingerprints. The
page shows the same SHA-256, and the setup refuses to change anything if the embedded certificate
does not match it, is not a CA, or is outside its dates.

### Trust mechanism

QZ Tray trusts its built-in QZ root plus every root listed in `authcert.override` (a `;`-separated list,
`^` escapes) and `override.crt` in its install folder. A certificate that chains to one of these is
trusted and added to `allowed.dat` without a prompt. `allowed.dat` alone does not help a certificate
QZ cannot chain: it still prompts.

ARK Print Setup does what QZ's own `ca` provisioning step does: it stores the root in its own folder
and appends that path to `authcert.override`. It does not use `override.crt`, which QZ's Site Manager
demo-certificate flow overwrites and which holds only one root.

| QZ Tray | Read from |
| --- | --- |
| 2.1.6 – 2.2.2 | `authcert.override` in `qz-tray.properties`, `-DtrustedRootCert`, `override.crt` |
| 2.2.3 – 2.2.5 | `authcert.override` (also `trustedRootCert`) in `qz-tray.properties` or as a system property, `override.crt` |
| older | not supported by the setup; update QZ Tray |

`qz-tray.properties` is the first that exists of: the install folder, the shared folder, the user folder.

| | Windows | macOS |
| --- | --- | --- |
| QZ Tray | `HKLM\SOFTWARE\QZ Tray` (version from the `QZ Tray` uninstall entry), else `C:\Program Files\QZ Tray` | `/Applications/QZ Tray.app` (version from `Info.plist`) |
| Settings | `<install>\qz-tray.properties`, `%ProgramData%\qz\`, `%APPDATA%\qz\` | `QZ Tray.app/Contents/Resources/qz-tray.properties`, `/Library/Application Support/qz/`, `~/Library/Application Support/qz/` |
| ARK roots | `%ProgramData%\ARK\Printing\trust\ark-<host>-<sha256 prefix>.crt` (SYSTEM and Administrators full, Users read) | `/Library/Application Support/ARK/Printing/trust/…` (root:wheel 644) |
| QZ log | `%APPDATA%\qz\debug.log` | `~/Library/Application Support/qz/debug.log` |

### What a run changes

- Adds one entry to `authcert.override` (creating the line if absent). Every other entry, including
  other applications' roots with their escapes and options, is written back exactly as it was.
- Replaces ARK's earlier root for the **same shop host** (certificate rotation) and says so. ARK roots for
  other shops on the same computer are kept.
- Backs up the file first (`qz-tray.properties.ark-backup-<UTC time>-<pid>`, five kept), keeps its
  line endings, owner and permissions, and changes nothing when the entry is already there.
- Refuses, changing nothing (exit 8), when `authcert.override` continues over several lines or appears
  twice, or when only a `trustedRootCert` entry exists.
- Restarts QZ Tray as the signed-in user when something changed (or starts it), then watches QZ's log
  for `Adding CA certificate: … (<sha1>)`.

Options: `-Status` / `--status` (report only), `-Uninstall` / `--uninstall` (remove this shop's entry
and file, restart QZ). Exit codes: 0 done, 3 QZ Tray not installed (the official download page opens),
4 QZ Tray too old, 5 settings not found (start QZ Tray once), 6 certificate check failed, 7 administrator
approval declined, 8 layout ARK will not edit, 1 other.

A QZ Tray reinstall or upgrade rewrites `qz-tray.properties` and keeps only `file.allow`,
`file.whitelist`, networking and steal settings, so run ARK Print Setup again after updating QZ Tray.

## Telling failures apart

| What staff see | Cause | Fix |
| --- | --- | --- |
| Every print asks to Allow; Set Up Printing says the certificate is not ready on the server | Server has no usable pair (`signing_ready=no`): the page falls back to an unsigned request | Restore or generate the shop's pair; `ark:printing:qz-check` |
| Check connection: "QZ Tray is not running" | Browser cannot reach QZ Tray on `localhost` | Install or start QZ Tray |
| Check connection: "does not trust this shop yet", or QZ asks to Allow | Workstation has not trusted the root (or QZ was reinstalled) | Run ARK Print Setup |
| "The ARK server could not sign the request" | Sign endpoint error (key unreadable, session expired) | `ark:printing:qz-check`, server logs `qz.sign_message_failed` |
| QZ log: `Bad signature on request` | Key on the server does not belong to the certificate | `private_key_matches_certificate`; put the matching pair together |

## Verification

`tests/Feature/Operations/Printing/QzPrintingSetupTest.php` signs with a shop pair, refuses mismatched
and expired pairs, and runs both setup scripts against copies of QZ Tray's file layout (install, repeat,
status, rotation, refusals, uninstall). The Windows script test needs `pwsh` (`ARK_TEST_PWSH`).
