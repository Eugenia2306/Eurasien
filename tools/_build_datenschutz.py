import re
from pathlib import Path

imp = Path(__file__).resolve().parent.parent / "static-site/impressum.html"
out_path = Path(__file__).resolve().parent.parent / "static-site/datenschutz.html"

main = """<main id="main">
<section class="page" id="p-datenschutz">
  <div class="phead"><div class="wrap"><p class="crumb"><a href="index.html">Start</a> › <a href="impressum.html"><span class="de">Impressum</span><span class="en" hidden>Imprint</span></a> › <span class="de">Datenschutz</span><span class="en" hidden>Privacy</span></p><p class="eyebrow"><span class="de">Rechtliches</span><span class="en" hidden>Legal</span></p><h1><span class="de">Datenschutz</span><span class="en" hidden>Privacy Policy</span></h1><p><span class="de">Informationen zum Umgang mit personenbezogenen Daten auf dieser Website.</span><span class="en" hidden>Information on how personal data is handled on this website.</span></p></div></div>

  <section class="sec"><div class="wrap" style="max-width:820px">
    <div class="prose" style="margin-top:24px">
      <p class="muted"><span class="de">Diese Hinweise beschreiben den Umgang mit personenbezogenen Daten auf der Website der Eurasien Gesellschaft e. V. Eine rechtsverbindliche Fassung muss vor dem Produktivbetrieb durch den Verein freigegeben werden.</span><span class="en" hidden>These notes describe how personal data is handled on the website of Eurasien Gesellschaft e. V. A legally binding version must be approved by the association before go-live.</span></p>

      <h3><span class="de">1. Verantwortlicher</span><span class="en" hidden>1. Data controller</span></h3>
      <p>Eurasien Gesellschaft e. V.<br>Kunz-Buntschuh-Straße 11<br>14193 Berlin, Deutschland<br><span class="de">E-Mail:</span><span class="en" hidden>Email:</span> <a href="mailto:kontakt@eurasien-gesellschaft.org">kontakt@eurasien-gesellschaft.org</a></p>

      <h3><span class="de">2. Hosting und Logfiles</span><span class="en" hidden>2. Hosting and log files</span></h3>
      <p><span class="de">Beim Besuch der Website speichert der Hoster technisch notwendige Server-Logfiles (z.&nbsp;B. IP-Adresse, Zeitpunkt, aufgerufene URL, User-Agent). Rechtsgrundlage: berechtigtes Interesse an Betrieb und Sicherheit (Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;f DSGVO).</span><span class="en" hidden>When you visit the website, the host stores technically necessary server log files (e.g. IP address, time, URL accessed, user agent). Legal basis: legitimate interest in operation and security (Art. 6(1)(f) GDPR).</span></p>

      <h3><span class="de">3. Mitgliederbereich, Bestellungen und Zahlungen</span><span class="en" hidden>3. Members area, orders and payments</span></h3>
      <p><span class="de">Login, Mitgliedschaft und Tickets laufen über WordPress unter <code>/app/</code> mit WooCommerce. Dabei werden Kontodaten (Name, E-Mail, Rechnungsadresse) und Bestelldaten verarbeitet. Zahlungen werden über Stripe abgewickelt. Stripe tritt als eigener Verantwortlicher bzw. Auftragsverarbeiter auf; es gelten die Stripe-Datenschutzinformationen. Rechtsgrundlage: Vertragserfüllung (Art.&nbsp;6 Abs.&nbsp;1 lit.&nbsp;b DSGVO).</span><span class="en" hidden>Login, membership and tickets run via WordPress at <code>/app/</code> with WooCommerce. Account data (name, email, billing address) and order data are processed. Payments are handled via Stripe. Stripe acts as its own controller or processor; Stripe's privacy information applies. Legal basis: contract performance (Art. 6(1)(b) GDPR).</span></p>

      <h3><span class="de">4. Cookies und Konto-Sitzungen</span><span class="en" hidden>4. Cookies and account sessions</span></h3>
      <p><span class="de">Für den Warenkorb, die Anmeldung und die Sitzungssicherheit setzt WooCommerce technisch notwendige Cookies. Analyse- oder Marketing-Cookies werden derzeit nicht eingesetzt.</span><span class="en" hidden>WooCommerce sets technically necessary cookies for the cart, login and session security. Analytics or marketing cookies are not currently used.</span></p>

      <h3><span class="de">5. YouTube / Mediathek</span><span class="en" hidden>5. YouTube / Media Library</span></h3>
      <p><span class="de">Verlinkte oder eingebettete YouTube-Inhalte können Daten an Google/YouTube übertragen. Einbettungen sollen erst nach aktiver Zustimmung geladen werden (Zwei-Klick-Lösung). Bis dahin genügen externe Links.</span><span class="en" hidden>Linked or embedded YouTube content may transfer data to Google/YouTube. Embeds should load only after active consent (two-click solution). Until then, external links are sufficient.</span></p>

      <h3><span class="de">6. Kontakt per E-Mail</span><span class="en" hidden>6. Contact by email</span></h3>
      <p><span class="de">Wenn Sie uns per E-Mail kontaktieren, verarbeiten wir die von Ihnen mitgeteilten Daten zur Bearbeitung der Anfrage.</span><span class="en" hidden>If you contact us by email, we process the data you provide to handle your enquiry.</span></p>

      <h3><span class="de">7. Speicherdauer und Rechte</span><span class="en" hidden>7. Retention and your rights</span></h3>
      <p><span class="de">Bestell- und Mitgliedsdaten werden so lange gespeichert, wie gesetzliche Aufbewahrungsfristen oder die Mitgliedschaft es erfordern. Sie haben Rechte auf Auskunft, Berichtigung, Löschung, Einschränkung, Widerspruch und Datenübertragbarkeit sowie Beschwerde bei einer Aufsichtsbehörde.</span><span class="en" hidden>Order and membership data are stored for as long as statutory retention periods or membership require. You have rights of access, rectification, erasure, restriction, objection and data portability, and the right to lodge a complaint with a supervisory authority.</span></p>

      <p class="muted" style="margin-top:32px"><a href="impressum.html"><span class="de">Impressum</span><span class="en" hidden>Imprint</span></a> · <a href="index.html"><span class="de">Start</span><span class="en" hidden>Home</span></a></p>
    </div>
  </div></section>
</section>
</main>"""

text = imp.read_text(encoding="utf-8")
text = text.replace("Impressum | Eurasien Gesellschaft", "Datenschutz | Eurasien Gesellschaft")
text = text.replace(
    'content="Unabhängige, gemeinnützige Berliner Plattform für Dialog, Wissenschaft, Kultur, Wirtschaft und Geopolitik im eurasischen Raum."',
    'content="Datenschutzhinweise der Eurasien Gesellschaft e. V."',
)
text = re.sub(r"<main id=\"main\">.*?</main>", main, text, count=1, flags=re.S)
text = re.sub(r"\n<div class=\"regmodal\".*?</div> </div>\n+", "\n", text, count=1, flags=re.S)
out_path.write_text(text, encoding="utf-8")
print("written", out_path)
