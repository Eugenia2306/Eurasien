# -*- coding: utf-8 -*-
"""Central SEO + GEO configuration for the static brochure site."""

from __future__ import annotations

SITE_NAME = "Eurasien Gesellschaft e. V."
SITE_BASE = "https://www.eurasien-gesellschaft.org"
DEFAULT_OG_IMAGE = f"{SITE_BASE}/assets/images/embed-62c28610d17731c2.png"
LOGO_URL = DEFAULT_OG_IMAGE

ORG = {
    "@id": f"{SITE_BASE}/#organization",
    "name": SITE_NAME,
    "alternateName": "Eurasien Gesellschaft",
    "url": f"{SITE_BASE}/",
    "email": "kontakt@eurasien-gesellschaft.org",
    "foundingDate": "2021",
    "description": (
        "Unabhängige, gemeinnützige Berliner Think-Tank-Plattform für Dialog, "
        "Analyse und Verständigung im eurasischen Raum: Kultur, Wissenschaft, "
        "Wirtschaft und Geopolitik."
    ),
    "address": {
        "@type": "PostalAddress",
        "streetAddress": "Kunz-Buntschuh-Straße 11",
        "postalCode": "14193",
        "addressLocality": "Berlin",
        "addressCountry": "DE",
    },
    "sameAs": [
        "https://www.linkedin.com/company/eurasien-gesellschaft/",
        "https://www.youtube.com/@EurasienGesellschaft",
        "https://t.me/EurasienGesellschaft",
    ],
}

# rel path from static-site/ -> metadata
# changefreq/priority for sitemap; robots index|noindex; schema hints
PAGES: dict[str, dict] = {
    "index.html": {
        "title": "Eurasien Gesellschaft e. V.",
        "description": (
            "Unabhängiger, gemeinnütziger Think Tank für Dialog und Verständigung "
            "zwischen Europa und Eurasien. Analysen zu Geopolitik, Energie, "
            "Wirtschaft, Wissenschaft und Kultur."
        ),
        "changefreq": "weekly",
        "priority": "1.0",
        "schema": "home",
    },
    "mission.html": {
        "title": "Mission | Eurasien Gesellschaft",
        "description": (
            "Mission und Selbstverständnis der Eurasien Gesellschaft e. V.: "
            "Dialog, differenzierte Analyse und Brücken zwischen Europa und Eurasien."
        ),
        "changefreq": "monthly",
        "priority": "0.8",
        "schema": "about",
        "breadcrumbs": [("Start", "/"), ("Mission", "/mission.html")],
    },
    "vorstand.html": {
        "title": "Vorstand | Eurasien Gesellschaft",
        "description": (
            "Vorstand, öffentliche Vertreter und Expertennetzwerk der Eurasien "
            "Gesellschaft e. V. – Profile, Rollen und fachliche Schwerpunkte."
        ),
        "changefreq": "monthly",
        "priority": "0.8",
        "schema": "about",
        "breadcrumbs": [("Start", "/"), ("Vorstand & Experten", "/vorstand.html")],
    },
    "partner.html": {
        "title": "Veranstaltungskooperationen | Eurasien Gesellschaft",
        "description": (
            "Partner und Veranstaltungskooperationen der Eurasien Gesellschaft: "
            "Netzwerk für Dialog und gemeinsame Formate im eurasischen Raum."
        ),
        "changefreq": "monthly",
        "priority": "0.6",
        "schema": "webpage",
        "breadcrumbs": [("Start", "/"), ("Veranstaltungskooperationen", "/partner.html")],
    },
    "gesellschaftsnachrichten.html": {
        "title": "Gesellschaftsnachrichten | Eurasien Gesellschaft",
        "description": (
            "Organisatorische Neuigkeiten der Eurasien Gesellschaft e. V. – "
            "getrennt von inhaltlichen Analysen und Stellungnahmen."
        ),
        "changefreq": "weekly",
        "priority": "0.6",
        "schema": "webpage",
        "breadcrumbs": [("Start", "/"), ("Gesellschaftsnachrichten", "/gesellschaftsnachrichten.html")],
    },
    "themen.html": {
        "title": "Themen | Eurasien Gesellschaft",
        "description": (
            "Themenschwerpunkte der Eurasien Gesellschaft: Geopolitik, Energie, "
            "Wirtschaft, Wissenschaft, Kultur sowie Länder und Gesellschaften."
        ),
        "changefreq": "monthly",
        "priority": "0.8",
        "schema": "webpage",
        "breadcrumbs": [("Start", "/"), ("Themen", "/themen.html")],
    },
    "themen/geopolitik.html": {
        "title": "Geopolitik | Eurasien Gesellschaft",
        "description": (
            "Geopolitik im eurasischen Raum: Analysen, Veranstaltungen und "
            "Expertenperspektiven der Eurasien Gesellschaft zu Ordnung, "
            "Sicherheit und internationalen Beziehungen."
        ),
        "changefreq": "weekly",
        "priority": "0.9",
        "schema": "topic",
        "breadcrumbs": [("Start", "/"), ("Themen", "/themen.html"), ("Geopolitik", "/themen/geopolitik.html")],
    },
    "themen/energie.html": {
        "title": "Energie | Eurasien Gesellschaft",
        "description": (
            "Energiepolitik und Versorgung im eurasischen Raum: Themen, "
            "Quellen und Analysen der Eurasien Gesellschaft."
        ),
        "changefreq": "weekly",
        "priority": "0.9",
        "schema": "topic",
        "breadcrumbs": [("Start", "/"), ("Themen", "/themen.html"), ("Energie", "/themen/energie.html")],
    },
    "themen/wirtschaft.html": {
        "title": "Wirtschaft | Eurasien Gesellschaft",
        "description": (
            "Wirtschaftliche Entwicklungen, Handel und Infrastruktur zwischen "
            "Europa und Eurasien – Themenfeld der Eurasien Gesellschaft."
        ),
        "changefreq": "weekly",
        "priority": "0.9",
        "schema": "topic",
        "breadcrumbs": [("Start", "/"), ("Themen", "/themen.html"), ("Wirtschaft", "/themen/wirtschaft.html")],
    },
    "themen/wissenschaft.html": {
        "title": "Wissenschaft | Eurasien Gesellschaft",
        "description": (
            "Wissenschaftlicher Austausch und internationale Kooperation im "
            "eurasischen Raum – Forschung, Bildung und Wissenstransfer."
        ),
        "changefreq": "weekly",
        "priority": "0.9",
        "schema": "topic",
        "breadcrumbs": [("Start", "/"), ("Themen", "/themen.html"), ("Wissenschaft", "/themen/wissenschaft.html")],
    },
    "kultur.html": {
        "title": "Kultur | Eurasien Gesellschaft",
        "description": (
            "Kultureller Austausch zwischen Europa und Eurasien: Formate, "
            "Quellen und Perspektiven der Eurasien Gesellschaft."
        ),
        "changefreq": "weekly",
        "priority": "0.8",
        "schema": "topic",
        "breadcrumbs": [("Start", "/"), ("Kultur", "/kultur.html")],
    },
    "laender-gesellschaften.html": {
        "title": "Länder & Gesellschaften | Eurasien Gesellschaft",
        "description": (
            "Länder- und Gesellschaftsprofile im eurasischen Raum: amtliche "
            "Quellen, Studien und Hintergründe von Armenien bis Usbekistan."
        ),
        "changefreq": "weekly",
        "priority": "0.8",
        "schema": "topic",
        "breadcrumbs": [("Start", "/"), ("Länder & Gesellschaften", "/laender-gesellschaften.html")],
    },
    "analysen.html": {
        "title": "Analysen | Eurasien Gesellschaft",
        "description": (
            "Aktuelles, Stellungnahmen, Positionen, Dossiers und Studien der "
            "Eurasien Gesellschaft zu Entwicklungen in Europa und Eurasien."
        ),
        "changefreq": "daily",
        "priority": "0.9",
        "schema": "collection",
        "breadcrumbs": [("Start", "/"), ("Analysen", "/analysen.html")],
    },
    "regionen.html": {
        "title": "Regionen | Eurasien Gesellschaft",
        "description": (
            "Regionale Einordnung im eurasischen Raum: Osteuropa, Zentralasien, "
            "Ostasien, Südasien, Naher Osten und mehr – mit Quellen und Veranstaltungen."
        ),
        "changefreq": "weekly",
        "priority": "0.8",
        "schema": "webpage",
        "breadcrumbs": [("Start", "/"), ("Regionen", "/regionen.html")],
    },
    "veranstaltungen.html": {
        "title": "Veranstaltungen | Eurasien Gesellschaft",
        "description": (
            "Veranstaltungskalender der Eurasien Gesellschaft: Konferenzen, "
            "Fachgespräche, Vorträge und Diskussionen zu Eurasien-Themen."
        ),
        "changefreq": "daily",
        "priority": "0.9",
        "schema": "events",
        "breadcrumbs": [("Start", "/"), ("Veranstaltungen", "/veranstaltungen.html")],
    },
    "mediathek.html": {
        "title": "Mediathek | Eurasien Gesellschaft",
        "description": (
            "Mediathek der Eurasien Gesellschaft: Videos, Aufzeichnungen und "
            "multimediale Beiträge zu Dialog und Analyse im eurasischen Raum."
        ),
        "changefreq": "weekly",
        "priority": "0.7",
        "schema": "webpage",
        "breadcrumbs": [("Start", "/"), ("Mediathek", "/mediathek.html")],
    },
    "aufzeichnungen.html": {
        "title": "Aufzeichnungen | Eurasien Gesellschaft",
        "description": (
            "Videoaufzeichnungen von Veranstaltungen und Fachgesprächen der "
            "Eurasien Gesellschaft e. V."
        ),
        "changefreq": "weekly",
        "priority": "0.7",
        "schema": "webpage",
        "breadcrumbs": [("Start", "/"), ("Aufzeichnungen", "/aufzeichnungen.html")],
    },
    "mitgliedschaft.html": {
        "title": "Mitgliedschaft | Eurasien Gesellschaft",
        "description": (
            "Mitglied werden oder Leserzugang wählen: Unterstützen Sie die "
            "Eurasien Gesellschaft e. V. und erhalten Sie Zugang zu vertiefenden Inhalten."
        ),
        "changefreq": "monthly",
        "priority": "0.7",
        "schema": "membership",
        "breadcrumbs": [("Start", "/"), ("Mitgliedschaft", "/mitgliedschaft.html")],
    },
    "mitgliedschaft-vorteile.html": {
        "title": "Mitgliedschaft & Vorteile | Eurasien Gesellschaft",
        "description": (
            "Vorteile der Mitgliedschaft und des Leserzugangs bei der Eurasien "
            "Gesellschaft: Formate, Inhalte und Beteiligungsmöglichkeiten."
        ),
        "changefreq": "monthly",
        "priority": "0.6",
        "schema": "webpage",
        "breadcrumbs": [("Start", "/"), ("Mitgliedschaft & Vorteile", "/mitgliedschaft-vorteile.html")],
    },
    "impressum.html": {
        "title": "Impressum | Eurasien Gesellschaft",
        "description": (
            "Impressum der Eurasien Gesellschaft e. V., Berlin: Vereinsangaben, "
            "Vertretung, Kontakt und rechtliche Hinweise."
        ),
        "changefreq": "yearly",
        "priority": "0.3",
        "schema": "legal",
        "breadcrumbs": [("Start", "/"), ("Impressum", "/impressum.html")],
    },
    "datenschutz.html": {
        "title": "Datenschutz | Eurasien Gesellschaft",
        "description": (
            "Datenschutzhinweise der Eurasien Gesellschaft e. V.: Umgang mit "
            "personenbezogenen Daten, Cookies, Mitgliedschaft und Zahlungen."
        ),
        "changefreq": "yearly",
        "priority": "0.3",
        "schema": "legal",
        "breadcrumbs": [("Start", "/"), ("Datenschutz", "/datenschutz.html")],
    },
    "anmelden.html": {
        "title": "Anmelden | Eurasien Gesellschaft",
        "description": "Anmeldung zum Mitgliederbereich der Eurasien Gesellschaft e. V.",
        "robots": "noindex,follow",
        "sitemap": False,
        "changefreq": "yearly",
        "priority": "0.1",
        "schema": "webpage",
    },
    "personen/alexander-rahr.html": {
        "title": "Alexander Rahr | Eurasien Gesellschaft",
        "description": (
            "Profil von Alexander Rahr: Gründer und Vorstandsmitglied der "
            "Eurasien Gesellschaft, Russland- und Osteuropaexperte, Autor."
        ),
        "og_image": f"{SITE_BASE}/assets/images/embed-1c8fff076f476cde.jpg",
        "changefreq": "monthly",
        "priority": "0.7",
        "schema": "person",
        "person": {
            "name": "Alexander Rahr",
            "jobTitle": "Gründer · Mitglied des Vorstands",
            "image": f"{SITE_BASE}/assets/images/embed-1c8fff076f476cde.jpg",
        },
        "breadcrumbs": [
            ("Start", "/"),
            ("Vorstand & Experten", "/vorstand.html"),
            ("Alexander Rahr", "/personen/alexander-rahr.html"),
        ],
    },
    "personen/alexander-neu.html": {
        "title": "Dr. Alexander S. Neu | Eurasien Gesellschaft",
        "description": (
            "Profil von Dr. Alexander S. Neu: Stellvertretender Vorsitzender "
            "der Eurasien Gesellschaft, Politik- und Sozialwissenschaftler."
        ),
        "og_image": f"{SITE_BASE}/assets/images/embed-ad269a9d3e4551c9.jpg",
        "changefreq": "monthly",
        "priority": "0.7",
        "schema": "person",
        "person": {
            "name": "Dr. Alexander S. Neu",
            "jobTitle": "Stellvertretender Vorsitzender",
            "image": f"{SITE_BASE}/assets/images/embed-ad269a9d3e4551c9.jpg",
        },
        "breadcrumbs": [
            ("Start", "/"),
            ("Vorstand & Experten", "/vorstand.html"),
            ("Dr. Alexander S. Neu", "/personen/alexander-neu.html"),
        ],
    },
    "personen/christoph-polajner.html": {
        "title": "Christoph Polajner | Eurasien Gesellschaft",
        "description": (
            "Profil von Christoph Polajner: Stellvertretender Vorsitzender, "
            "Schwerpunkt China und internationale Ordnung."
        ),
        "og_image": f"{SITE_BASE}/assets/images/embed-d275d324480f2a24.jpg",
        "changefreq": "monthly",
        "priority": "0.7",
        "schema": "person",
        "person": {
            "name": "Christoph Polajner",
            "jobTitle": "Stellvertretender Vorsitzender",
            "image": f"{SITE_BASE}/assets/images/embed-d275d324480f2a24.jpg",
        },
        "breadcrumbs": [
            ("Start", "/"),
            ("Vorstand & Experten", "/vorstand.html"),
            ("Christoph Polajner", "/personen/christoph-polajner.html"),
        ],
    },
    "personen/christian-wipperfuerth.html": {
        "title": "Dr. Christian Wipperfürth | Eurasien Gesellschaft",
        "description": (
            "Profil von Dr. Christian Wipperfürth: Vorstandsmitglied der "
            "Eurasien Gesellschaft, Historiker und Publizist."
        ),
        "og_image": f"{SITE_BASE}/assets/images/embed-45b228237a484272.jpg",
        "changefreq": "monthly",
        "priority": "0.7",
        "schema": "person",
        "person": {
            "name": "Dr. Christian Wipperfürth",
            "jobTitle": "Mitglied des Vorstands",
            "image": f"{SITE_BASE}/assets/images/embed-45b228237a484272.jpg",
        },
        "breadcrumbs": [
            ("Start", "/"),
            ("Vorstand & Experten", "/vorstand.html"),
            ("Dr. Christian Wipperfürth", "/personen/christian-wipperfuerth.html"),
        ],
    },
    "personen/andreas-schraps.html": {
        "title": "Andreas Schraps | Eurasien Gesellschaft",
        "description": (
            "Profil von Andreas Schraps: Vorstandsmitglied und Geschäftsführer "
            "der Eurasien Gesellschaft e. V."
        ),
        "og_image": f"{SITE_BASE}/assets/images/embed-4c235706773276e3.jpg",
        "changefreq": "monthly",
        "priority": "0.7",
        "schema": "person",
        "person": {
            "name": "Andreas Schraps",
            "jobTitle": "Mitglied des Vorstands · Geschäftsführer",
            "image": f"{SITE_BASE}/assets/images/embed-4c235706773276e3.jpg",
        },
        "breadcrumbs": [
            ("Start", "/"),
            ("Vorstand & Experten", "/vorstand.html"),
            ("Andreas Schraps", "/personen/andreas-schraps.html"),
        ],
    },
    "mitglieder/dossiers.html": {
        "title": "Dossiers | Eurasien Gesellschaft",
        "description": "Mitgliederbereich: Dossiers der Eurasien Gesellschaft (Zugang erforderlich).",
        "robots": "noindex,follow",
        "sitemap": False,
        "schema": "webpage",
    },
    "mitglieder/positionen.html": {
        "title": "Positionen | Eurasien Gesellschaft",
        "description": "Mitgliederbereich: Positionspapiere der Eurasien Gesellschaft (Zugang erforderlich).",
        "robots": "noindex,follow",
        "sitemap": False,
        "schema": "webpage",
    },
    "mitglieder/studien.html": {
        "title": "Studien | Eurasien Gesellschaft",
        "description": "Mitgliederbereich: Studien der Eurasien Gesellschaft (Zugang erforderlich).",
        "robots": "noindex,follow",
        "sitemap": False,
        "schema": "webpage",
    },
}

FAQ_HOME = [
    {
        "q": "Was ist die Eurasien Gesellschaft e. V.?",
        "a": (
            "Die Eurasien Gesellschaft e. V. ist ein unabhängiger, gemeinnütziger "
            "Think Tank mit Sitz in Berlin. Sie fördert Dialog, Analyse und "
            "Verständigung zwischen Europa und dem eurasischen Raum in den Feldern "
            "Geopolitik, Energie, Wirtschaft, Wissenschaft und Kultur."
        ),
    },
    {
        "q": "Ist die Eurasien Gesellschaft parteipolitisch gebunden?",
        "a": (
            "Nein. Die Gesellschaft versteht sich als unabhängige, gemeinnützige "
            "Plattform für sachliche Analyse und Dialog – ohne Interessenvertretung "
            "einzelner Staaten oder Organisationen."
        ),
    },
    {
        "q": "Wie kann ich Mitglied werden?",
        "a": (
            "Interessierte können über die Seite Mitgliedschaft eine Vereinsmitgliedschaft "
            "beantragen oder einen kostenpflichtigen Leserzugang für vertiefende Inhalte wählen."
        ),
    },
    {
        "q": "Wo findet man Veranstaltungen und Analysen?",
        "a": (
            "Der Veranstaltungskalender listet kommende und vergangene Formate. "
            "Unter Analysen sind Aktuelles, Stellungnahmen, Positionen, Dossiers und Studien gebündelt."
        ),
    },
]
