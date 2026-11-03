#!/usr/bin/env python3
"""Split the existing WP-Opsec downloads page into a portal and platform guides."""

from html import escape
from pathlib import Path
import json
import re

ROOT = Path(__file__).resolve().parent
backup = ROOT / "backups" / "downloads-before-platform-split-2026-10-22.json"
source = json.loads(backup.read_text(encoding="utf-8"))["post_content"]

style = source[source.index("<style>"):source.index("</style>") + len("</style>")]
style = style.replace("</style>", """
.thessley-downloads .td-hero h1{margin:.2rem 0 .7rem;color:#fff;font-size:clamp(2rem,5vw,3rem)}
.thessley-downloads details{margin:1rem 0;padding:0 1rem 1rem;border:1px solid var(--td-line);border-radius:12px;background:#121e30}
.thessley-downloads summary{padding:1rem 0;color:#f5f8fc;font-size:1.1rem;font-weight:750;cursor:pointer}
.thessley-downloads details[open]>summary{border-bottom:1px solid var(--td-line)}
</style>""")
copy_js = source[source.index("<script>"):source.index("</script>") + len("</script>")]


def section(section_id: str) -> str:
    start = source.index(f'<section id="{section_id}">')
    end = source.index("</section>", start) + len("</section>")
    return source[start:end]


wordpress = section("wordpress")
android = section("android")
windows = section("windows")
linux = section("linux")

# The landing page is a project and download index; platform setup lives on its own page.
portal = f"""<!-- wp:html -->
{style}
<main class="thessley-downloads alignwide">
  <section class="td-hero">
    <p class="td-kicker">Thessley security projects</p>
    <h1>Downloads and installation</h1>
    <p>Choose a platform guide for the Thessley approval service. Each guide starts with a guided installer; manual setup instructions are available below it.</p>
  </section>
  <section>
    <h2>Install the computer service</h2>
    <div class="td-card">
      <h3>Ubuntu 24.04 · Linux</h3>
      <p>Signed APT repository and guided installer, with source archive instructions as an alternative.</p>
      <a class="td-download" href="?page_id=42">Linux installation guide</a>
    </div>
    <div class="td-card">
      <h3>Windows 11</h3>
      <p>PowerShell guided installer for the Windows approval service. Optional components default to off.</p>
      <a class="td-download" href="?page_id=43">Windows installation guide</a>
    </div>
  </section>
  {wordpress}
  {android}
  <p><a href="https://wp-opsec.eagleeye-intelligence.com/wp-content/uploads/thessley-downloads/SHA256SUMS.txt">Linux and Android SHA-256 checksums</a> · <a href="https://wp-opsec.eagleeye-intelligence.com/wp-content/uploads/thessley-windows-downloads/SHA256SUMS.txt">Windows package checksums</a></p>
</main>
{copy_js}
<!-- /wp:html -->
"""

windows_command = "irm https://wp-opsec.eagleeye-intelligence.com/wp-content/uploads/thessley-windows-downloads/install.ps1 | iex"
linux_command = "sudo install -d -m 0755 /etc/apt/keyrings && curl -fsSL https://wp-opsec.eagleeye-intelligence.com/wp-content/uploads/thessley-apt/thessley-archive-keyring.gpg | sudo tee /etc/apt/keyrings/thessley-archive-keyring.gpg >/dev/null && sudo chmod 0644 /etc/apt/keyrings/thessley-archive-keyring.gpg && echo 'deb [arch=amd64,arm64 signed-by=/etc/apt/keyrings/thessley-archive-keyring.gpg] https://wp-opsec.eagleeye-intelligence.com/wp-content/uploads/thessley-apt stable main' | sudo tee /etc/apt/sources.list.d/thessley.list >/dev/null && sudo apt update && sudo apt install -y thessley-pam-linux-installer && sudo thessley-pam-install"

def guide(title: str, target: str, command: str, intro: str, alternative: str, details: str, extra: str = "", extra_before: bool = False, guided_title: str = "Guided one-line install") -> str:
    return f"""<!-- wp:html -->
{style}
<main class="thessley-downloads alignwide">
  <section class="td-hero">
    <p class="td-kicker">Thessley computer installation</p>
    <h1>{title}</h1>
    <p>{target}</p>
    <p><a href="?page_id=16">All downloads</a></p>
  </section>
  {extra if extra_before else ""}
  <section id="guided-install">
    <h2>{guided_title}</h2>
    <p>{intro}</p>
    <pre><code>{escape(command)}</code><button type="button" class="td-copy" aria-label="Copy command">Copy command</button></pre>
  </section>
  {"" if extra_before else extra}
  <details>
    <summary>{alternative}</summary>
    {details}
  </details>
</main>
{copy_js}
<!-- /wp:html -->
"""

# Remove the old quick-install subsection so each platform page has one primary command.
windows_details = re.sub(
    r'\s*<h3>Guided one-line install</h3>.*?</p>\s*<h3>Manual Windows SSH \+ Web GUI installation</h3>',
    "\n    <h3>Manual Windows SSH + Web GUI installation</h3>", windows, count=1, flags=re.S)
windows_details = re.sub(
    r'<li>Use Windows 11 and open PowerShell as Administrator\..*?See <a href="https://learn\.microsoft\.com/en-us/windows-server/administration/openssh/openssh_install_firstuse\?pivots=windows-11">Microsoft’s OpenSSH Server installation guide</a>\.</p>',
    '<li>Complete Step 1 above first. Confirm <code>sshd</code> is running and a normal SSH login works from the LAN before configuring Thessley.</li>\n    </ol>\n    <p>For OpenSSH details, see <a href="https://learn.microsoft.com/en-us/windows-server/administration/openssh/openssh_install_firstuse?pivots=windows-11">Microsoft’s OpenSSH Server installation guide</a>.</p>',
    windows_details, count=1, flags=re.S)
for broken, corrected in {
    "C:ThessleyWindows-SSH-WebGUI": r"C:\Thessley\Windows-SSH-WebGUI",
    "C:Program FilesPython313python.exe": r"C:\Program Files\Python313\python.exe",
    "C:ProgramDataThessleyPamWinthessley-pam-win.cmd": r"C:\ProgramData\ThessleyPamWin\thessley-pam-win.cmd",
    "C:ProgramDatasshsshd_config": r"C:\ProgramData\ssh\sshd_config",
    "C:Program FilesOpenSSHsshd.exe": r"C:\Program Files\OpenSSH\sshd.exe",
}.items():
    windows_details = windows_details.replace(broken, corrected)
windows_details = windows_details.replace('<section id="windows">', '<section id="windows-alternatives">', 1)
windows_details = windows_details.replace("</section>", "</section>", 1)

linux_details = re.sub(
    r'\s*<h3>Ubuntu 24\.04 APT repository.*?</p>\s*<h3>Rollback</h3>',
    "\n    <h3>Rollback</h3>", linux, count=1, flags=re.S)
linux_details = linux_details.replace('<section id="linux">', '<section id="linux-alternatives">', 1)
linux_details = linux_details.replace(
    "Reconnects require a new phone approval; this setup does not issue reconnect tokens or a grace period.",
    "After the first phone approval, OpenVPN reconnect tokens renew hourly and expire within 8 hours of the last renewal. The client certificate is still checked on each connection.",
)
linux_details = linux_details.replace(
    "This Ubuntu 24.04 setup requires both a per-device OpenVPN client certificate and a live approval on the enrolled phone for every connection. OpenVPN Connect 3.x can open the QR approval page automatically.",
    "This Ubuntu 24.04 setup requires a per-device OpenVPN client certificate and Thessley phone approval. OpenVPN Connect 3.x can open the QR approval page automatically.",
)
linux_details = linux_details.replace(
    'auth-user-pass-verify /usr/local/lib/thessley-pam/vpn-auth via-env\nreneg-sec 0\npush "reneg-sec 0"\nuser nobody\ngroup nogroup\nkeepalive 10 120',
    'crl-verify /etc/openvpn/easy-rsa-thessley/pki/crl.pem\nmanagement /run/openvpn-server/thessley.mgmt unix\nmanagement-client-user root\nscript-security 2\nauth-user-pass-verify /usr/local/lib/thessley-pam/vpn-auth via-env\nauth-gen-token 28800 3600\nauth-gen-token-secret /etc/openvpn/server/thessley-auth-token.key\nreneg-sec 3600\npush "reneg-sec 3600"\nuser nobody\ngroup nogroup\nkeepalive 10 300',
)
linux_details = linux_details.replace(
    '    </pre>\n    <ol start="4">',
    '    </pre>\n    <p>Before starting OpenVPN, create and protect the server-signed reconnect-token key. Keep this same key across reboots; replacing it invalidates active tokens:</p>\n    <pre><code>sudo openvpn --genkey auth-token /etc/openvpn/server/thessley-auth-token.key\nsudo chown root:root /etc/openvpn/server/thessley-auth-token.key\nsudo chmod 600 /etc/openvpn/server/thessley-auth-token.key</code></pre>\n    <ol start="4">',
    1,
)

windows_intro = "Install on the Windows PC you are setting up. Open PowerShell as Administrator there; this local setup does not require copying an SSH key to another computer. The command downloads the installer and checksum list, verifies the installer file, then runs that local verified file. The installer checks UTC clock accuracy before making setup changes; time zone settings do not affect that check. If the installer reports a clock difference, sync Windows time and rerun."
linux_intro = "For Ubuntu 24.04 LTS on amd64 or ARM64, paste this command in a terminal. It adds the signed Thessley APT repository, installs the package, and starts the wizard. Optional components default to No; existing SSH/PAM and OpenVPN server configuration are not changed automatically."
vpn_checksum = "b7fcb21006338ab4bdcb9cd524e12abcd2256c6cde21e48d9081877f15016807"
vpn_installer_url = "https://wp-opsec.eagleeye-intelligence.com/wp-content/uploads/thessley-downloads/install-openvpn-server.sh"
vpn_command = f"f=$(mktemp) && (trap 'rm -f \"$f\"' EXIT; curl -fsSL -o \"$f\" {vpn_installer_url} && printf '%s  %s\\n' '{vpn_checksum}' \"$f\" | sha256sum -c - && sudo bash \"$f\")"
vpn_extra = f'''<section id="openvpn-quick-install">
    <h2>Guided OpenVPN server install</h2>
    <p>First install the Thessley Linux service above. Then use this on a <strong>new Ubuntu 24.04 OpenVPN server</strong>. It verifies the installer checksum, asks for the public host, local account, tunnel subnet, UDP port and first client name, creates the compatible OpenVPN configuration, enrolls the phone by QR and matching code, and writes a protected client profile. It stops if a Thessley VPN config, PKI, or approval service already exists. It does not alter SSH, router, or firewall settings.</p>
    <pre><code>{escape(vpn_command)}</code></pre>
    <p>The server requires a client certificate and Thessley phone approval. After the first approval, OpenVPN reconnect tokens renew hourly and last up to 8 hours after renewal. The wizard prints the router ports to forward: the chosen UDP port and TCP 47830 for QR approval. Review <code>docs/VPN.md</code> in the source archive before adapting an existing VPN.</p>
  </section>'''

(ROOT / "downloads.html").write_text(portal, encoding="utf-8")
# windows-install.html and linux-install.html are maintained as the canonical
# platform guides; do not replace them with the older generated variants.
