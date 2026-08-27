# Open-ERP-System — Funktionsdesign-Dokument

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Systemübersicht

Das Open-ERP-System (open-erp) ist ein Full-Stack-Enterprise-Resource-Planning-System auf Basis von webman v2 + Flutter und deckt vierzehn große Geschäftsdomänen ab: Systemverwaltung, Einkauf/Verkauf/Lager, Finanzen, CRM, Genehmigungsworkflow, Nachrichten, Projektmanagement, Personalwesen, Produktion und Fertigung sowie benutzerdefinierte Berichte.

### 1.1 Designziele
- Monolithische Bereitstellung, modulares Design
- Alle IDs werden per snowflake erzeugt + per hashids verschlüsselt übertragen
- Doppelte Verschlüsselung sensibler Daten (Übertragungsschicht AES-256-CBC + Speicherschicht AES-128-ECB)
- Bewegte-Durchschnittskosten-Rechnung
- Modulübergreifende automatische Verknüpfung (Einkauf→Verbindlichkeiten, Vertrieb→Forderungen, Zahlungen→Verrechnung)

### 1.2 Technische Randbedingungen
- PHP 8.3+, MySQL 8.0+, Redis 7, Elasticsearch 8
- Tabellenpräfix erik_, Primärschlüssel BIGINT nicht auto-increment
- API-Version über den Request-Header API-Version gesteuert
- JWT-Authentifizierung + RBAC-Berechtigungen
- Globale Funktionen ohne \ -Präfix

---

## 2. Systemverwaltungsmodul

### 2.1 Benutzerverwaltung
- Administrator-CRUD, unterstützt Batch-Aktivieren/Deaktivieren, Batch-Soft-Delete
- Excel-Batch-Import (zeilenweise Validierung + Fehlerbericht)
- Passwort als bcrypt-Hash, Passwortänderung erfordert Bestätigung des alten Passworts
- Löschoperationen erfordern die zweite Bestätigung mit dem aktuellen Benutzerpasswort
- Handynummer/E-Mail/Personalausweisnummer verschlüsselt gespeichert, in Listen automatisch maskiert

### 2.2 Rollen und Berechtigungen (RBAC)
- Rollen-CRUD, slug als eindeutige Kennung
- Berechtigungsbaum (unbegrenzte parent_id-Selbstreferenz), Typen: Menü/Button/API
- Berechtigungskennung-Format: {method}.{path} (z. B. get.admin/product, post.admin/user/batch/destroy)
- Rolle-Berechtigung viele-zu-viele-Verknüpfung
- Super-Admin (super_admin) überspringt alle Berechtigungsprüfungen
- AdminPermission-Middleware cached Berechtigungen in Redis (TTL=60s)

### 2.3 Systemkonfiguration
- Schlüssel-Wert-Speicher, unterstützt Gruppierung
- Werttypen: string|int|bool|json|array

### 2.4 Betriebsprüfung
- Automatische Protokollierung aller POST/PUT/DELETE-Operationen
- Erfasst: Ausführenden, Aktion, Methode, Pfad, IP, Parameter (sensible Felder maskiert), Zeitpunkt
- Automatische Erkennung von 8 Plattform-Quellgeräten (Web/Flutter/HarmonyOS/API usw.)
- Nur Abfrage unterstützt, weder löschbar noch änderbar

### 2.5 Sicherheitsschutz
- 18 Ebenen Tiefenverteidigung (Details siehe SECURITY.md)
- SecurityFilter: HTTP-Methodeneinschränkung + XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF-Abfang
- RateLimit: Redis-Sliding-Window-Rate-Limit (Lua-Atomar, 60 Mal/Minute)
- Click-Captcha (Pflicht bei Login/Registrierung)
- Kontosperrung: 5 Fehlversuche sperren für 15 Minuten
- Begrenzung paralleler Sitzungen: maximal 3 Tokens pro Benutzer
- CSP-Header, security.txt (RFC 9116)
- poster-php Zufalls-Zweitverifikation bei sensiblen Operationen

---

## 3. Artikel und Stammdaten

### 3.1 Artikelverwaltung
- Artikelstamm: Code (eindeutig), Name, Barcode, Spezifikation, Basiseinheit, Bild, Beschreibung
- Multi-Spezifikations-SKU: mehrere SKUs pro Artikel, jeweils eigene Codes, Barcodes, Spezifikationsattribute (JSON)
- Multi-Einheiten-Umrechnung: Basiseinheit ↔ Hilfseinheit, Umrechnungsrate
- Preisstrategien: Einkaufspreis, Großhandelspreis, Einzelhandelspreis, Kundenstufenpreis
- Artikelkategorien: unbegrenzte Baumstruktur, Drag-&-Drop-Sortierung wird unterstützt
- Markenverwaltung: Markenname, Logo, Beschreibung

### 3.2 Lager und Lagerplätze
- Multi-Lager-Verwaltung (Name, Code, Adresse, Verantwortlicher)
- Mehrere Lagerplätze pro Lager (Code innerhalb des Lagers eindeutig)
- Kontakttelefon des Lagers verschlüsselt gespeichert

### 3.3 Lieferanten/Kunden
- Lieferantenstamm: Code, Name, Kontaktperson, Telefon/E-Mail (verschlüsselt), Adresse, Bankinformationen
- Kundenstamm: Code, Name, Kundenstufe, Kreditlimit
- Kundenstufe: Name, Standard-Rabattsatz
- Lieferanten/Kunden unterstützen ES-Volltextsuche

---

## 4. Einkaufsmodul

### 4.1 Einkaufsprozess
Anfrage → Genehmigung → Bestellung → Wareneingang → Abrechnung

### 4.2 Einkaufsanfrage
- Abteilung/Person reicht Einkaufsbedarf ein
- Status: Ausstehende Genehmigung → Genehmigt/Abgelehnt → In Bestellung umgewandelt
- Genehmiger-Operationen werden unterstützt

### 4.3 Einkaufsbestellung
- Verknüpft Lieferant, Artikelpositionen (Menge, Stückpreis, Betrag)
- Status: Ausstehende Prüfung → Geprüft → Teilweise empfangen → Empfangen → Storniert
- Kann auf Basis der Einkaufsanfrage oder direkt erstellt werden

### 4.4 Wareneingang (modulübergreifende Verknüpfung)
- Wareneingang nach Bestellung, Teil-Empfänge werden unterstützt
- Beim Empfang automatische Auslösung:
  1. InventoryService.stockIn() → Aktualisierung des Echtzeitbestands + Neuberechnung der bewegten Durchschnittskosten
  2. FinanceService.createAp() → Erzeugung der Verbindlichkeitsaufzeichnung
  3. Aktualisierung der empfangenen Menge und des Status der Bestellung
- Lagerplatz- und Chargennummern-Erfassung werden unterstützt

### 4.5 Einkaufsretoure
- Rückgabe an den Lieferanten, erzeugt eine Warenausgangs-Stornierung
- Verknüpft Wareneingangsbeleg

### 4.6 Lieferantenabrechnung
- Zusammenfassung pro Lieferant: Einkaufsbetrag, bezahlt, Verbindlichkeiten
- Abrechnungsstatus: Nicht abgerechnet/Teilweise abgerechnet/Abgerechnet

---

## 5. Vertriebsmodul

### 5.1 Vertriebsprozess
Angebot → Auftrag → Versand → Abrechnung

### 5.2 Angebot
- Angebot an den Kunden, Umwandlung in Verkaufsauftrag wird unterstützt
- Status: Entwurf → Angeboten → In Auftrag umgewandelt → Ungültig

### 5.3 Verkaufsauftrag
- Verknüpft Kunde, Artikelpositionen (Menge, Stückpreis, Rabatt)
- Status: Ausstehende Prüfung → Geprüft → Teilweise versendet → Versendet → Storniert
- Rabattbeträge werden unterstützt

### 5.4 Versand (modulübergreifende Verknüpfung)
- Versand nach Auftrag, Teil-Lieferungen werden unterstützt
- Beim Versand automatische Auslösung:
  1. InventoryService.stockOut() → Bestandsabzug (zu bewegten Durchschnittskosten)
  2. FinanceService.createAr() → Erzeugung der Forderungsaufzeichnung
  3. Aktualisierung der versendeten Menge und des Status des Auftrags

### 5.5 Verkaufsretoure
- Kundenretoure, erzeugt eine Wareneingangs-Stornierung

### 5.6 Kundenabrechnung und Rohertrag
- Zusammenfassung pro Kunde: Verkaufsbetrag, erhalten, Forderungen
- Verkaufsrohertrag: nach Auftrag/Artikel/Kunde berechnet

---

## 6. Bestandsmodul

### 6.1 Bestandsverwaltung
- Echtzeitbestand: vierdimensionale Genauigkeit Lager+Lagerplatz+Charge+SKU
- Ein-/Ausgangs-Buchungen: alle Bestandsänderungen werden einheitlich erfasst (Richtung, Menge, Einstandspreis, Quellbelegnummer)
- Chargen-Tracking: Produktionsdatum, Verfallsdatum
- Seriennummern-Tracking: eindeutige Seriennummern, Status (im Lager/ausgegeben) wird bei Ein-/Ausgang erfasst

### 6.2 Kostenrechnung
- Methode der bewegten Durchschnittskosten
- Formel: Neuer Durchschnittspreis = (bisheriger Gesamtbestandswert + aktueller Wareneingangs-Gesamtwert) / (bisherige Bestandsmenge + aktuelle Wareneingangsmenge)
- Bei jedem Wareneingang automatische Neuberechnung, beim Warenausgang Kosten zu aktuellem Durchschnittspreis
- Vollständige Kostenaufzeichnungskette (Durchschnittspreis vor Änderung → Durchschnittspreis nach Änderung)

### 6.3 Bestandsumlagerung
- Umlagerung zwischen Lagern/Lagerplätzen
- Status: Ausstehende Umlagerung → Ausgelagert → Eingelagert → Abgeschlossen
- Automatische Erzeugung der Auslagerungs-/Einlagerungs-Buchungen

### 6.4 Inventurverwaltung
- Geplante Inventur (nach Lager/Kategorie) + dynamische Inventur (nach SKU)
- Erfasst Buchmenge vs. Istmenge
- Inventurdifferenzen erzeugen automatisch Überbestands-/Fehlbestands-Buchungen

### 6.5 Bestandswarnungen
- Unter-/Obergrenzen pro SKU+Lager
- Bei Unterschreitung der Untergrenze/Überschreitung der Obergrenze wird automatisch ein Warnprotokoll erfasst

---

## 7. Finanzmodul

### 7.1 Kontenplan
- Kontenbaum: fünf Hauptkategorien Anlagen/Verbindlichkeiten/Eigenkapital/Erträge/Aufwendungen
- Kontonummer eindeutig
- Saldierungsrichtung: Soll/Haben

### 7.2 Buchungsbelege
- Belegnummer, Datum, Zusammenfassung
- Doppelte Buchführung: jede Buchung enthält Soll- und Haben-Betrag (Soll und Haben müssen sich ausgleichen)
- Status: Entwurf → Geprüft

### 7.3 Hauptbuch
- Zusammenfassung nach Konto + Abrechnungsperiode (Jahr/Monat)
- Erfasst: Eröffnungs-Soll-/Haben-Saldo, Perioden-Soll-/Haben-Umsätze, Schluss-Soll-/Haben-Saldo
- Schluss-Saldo = Eröffnungssaldo ± Perioden-Umsatz (nach Saldierungsrichtung des Kontos)
- Automatische Aktualisierung nach Belegprüfung
- Filterung nach Jahr/Monat/Konto wird unterstützt

### 7.4 Detailbuch
- Jede Belegbuchung eines bestimmten Kontos wird einzeln aufgezeichnet
- Enthält: Belegnummer, Richtung (Soll/Haben), Betrag, Saldo, Zusammenfassung, Datum
- Abfrage nach Konto + Datumsbereich wird unterstützt
- Synchron mit den Belegbuchungen aktualisiert

### 7.5 Bilanz
- Nach Abrechnungsperiode erzeugt (monatlich/jährlich)
- Automatische Zusammenfassung der Hauptbuch-Salden:
  - Anlagenkonten (1) → Gesamtanlagen = Umlaufvermögen + Anlagevermögen
  - Verbindlichkeitskonten (2) → Gesamtverbindlichkeiten = kurzfristige Verbindlichkeiten + langfristige Verbindlichkeiten
  - Eigenkapitalkonten (3) → Eigenkapital
  - Identitätsgleichung: Anlagen = Verbindlichkeiten + Eigenkapital
- Snapshot-Speicherung wird unterstützt (vollständige JSON-Daten)
- Ohne Snapshot automatische Erzeugung aus dem Hauptbuch

### 7.6 Kapitalflussrechnung
- Nach Abrechnungsperiode erzeugt (monatlich/jährlich)
- Drei Kategorien:
  - Cashflow aus betrieblicher Tätigkeit (Verkaufseinnahmen - Einkaufszahlungen - Aufwandsausgaben)
  - Cashflow aus Investitionstätigkeit
  - Cashflow aus Finanzierungstätigkeit
- Anfangs-/End-Kassenbestand = Summe der Anfangs-/End-Salden aller Bankkonten
- Automatische Zusammenfassung aus dem Kassen- und Banktagebuch
- Snapshot-Speicherung wird unterstützt (vollständige JSON-Daten)

### 7.7 Forderungen/Verbindlichkeiten
- Automatisch durch Wareneingang/Versand erzeugt
- Forderung: Typ=Forderung, verknüpft Kunde, Quelle=Verkaufslieferbeleg
- Verbindlichkeit: Typ=Verbindlichkeit, verknüpft Lieferant, Quelle=Einkaufswareneingangsbeleg
- Status: Nicht verrechnet → Teilweise verrechnet → Verrechnet
- Belege gleicher Quelle können nicht doppelt erzeugt werden (Idempotenzschutz)

### 7.8 Zahlungseingänge
- Mehrere Konten (Bargeld/Bank/WeChat/Alipay)
- Nach Prüfung automatische Aktualisierung des Bankkontosaldos und des Kassenjournals
- Verrechnung: Forderungsaufzeichnung auswählen, Verrechnungsbetrag eingeben (nicht höher als der unverrechnete Saldo)
- Statuswechsel bei Teilverrechnung automatisch

### 7.9 Zahlungsausgänge
- Gleiche Logik wie Zahlungseingänge, Richtung umgekehrt
- Verrechnung von Verbindlichkeiten

### 7.10 Kassen- und Banktagebuch
- Jede Einnahme/Ausgabe pro Konto + Datum aufgezeichnet
- Erfasst Saldo nach Änderung
- Bankkontosaldo wird in Echtzeit aktualisiert

### 7.11 Spesenabrechnung
- Prozess: Einreichung → Genehmigung → Auszahlung
- Verknüpft Aufwandskonto
- Nach der Auszahlung automatische Erzeugung des Zahlungsbelegs + Tagebuch

### 7.12 Gewinn- und Verlustrechnung
- Monatliche Zusammenfassung: Betriebseinnahmen, Betriebskosten, Aufwendungen, Gewinn
- Datensnapshot-Speicherung (year+month eindeutig)

### 7.13 Anlagevermögen-Abschreibung
- Vollständige Verwaltung des Anlagen-Lebenszyklus: Anschaffung → Nutzung → Abschreibung → Veräußerung
- Abschreibungsmethode: lineare Methode ((Anschaffungswert - Restwert) / Nutzungsmonate)
- Monatliche Abschreibungsabgrenzung, automatische Erzeugung der Abschreibungsaufzeichnungen
- Erfasst: Anschaffungswert, Restwert, Nutzungsdauer, monatlicher Abschreibungsbetrag, kumulierte Abschreibung, Nettobuchwert

### 7.14 Steuerverwaltung
- Mehrere Steuerarten werden unterstützt: Mehrwertsteuer/Körperschaftsteuer/Einkommensteuer/Stempelsteuer
- Steuersätze flexibel konfigurierbar
- Mit Einkaufs-/Verkaufsbelegen verknüpft, automatische Erfassung des Steuerbetrags

### 7.15 Multi-Währung
- Währungsverwaltung: CNY/USD/EUR/JPY usw.
- Kennzeichnung der Basiswährung
- Wechselkursverwaltung nach Gültigkeitsdatum
- Fremdwährungsumrechnung wird unterstützt

### 7.16 Budgetverwaltung
- Jahresbudgetplanung: nach Kostenstelle + Konto + Monat
- Budget-vs.-Ist-Vergleichsanalyse
- Ausführungsraten-Berechnung + Abweichungsanalyse
- Status: Entwurf → Genehmigt → In Ausführung → Geschlossen

### 7.17 Kosten-/Profit-Center
- Baumförmige Hierarchiestruktur
- Kostenzuordnung + Aufwandsumlage
- Unabhängige Abrechnung der Profit-Center

---

## 8. CRM-Modul

### 8.1 Kundenverwaltung
- Kundenstamm mit Kunden der Stammdaten verknüpft
- Mehrere Kontaktpersonen pro Kunde (Kennzeichnung der primären Kontaktperson)
- Kontakttelefon/E-Mail verschlüsselt gespeichert

### 8.2 Follow-up-Aufzeichnungen
- Follow-up-Methoden: Telefon/Besuch/E-Mail/Nachricht/Sonstige
- Erfasst Follow-up-Inhalt, geplante nächste Follow-up-Aktion, Zeitpunkt des nächsten Follow-ups
- Verknüpft Kunde, Kontaktperson, Chance

### 8.3 Marketingkampagnen
- Vollständiger Lebenszyklus der Kampagne: Geplant → Laufend → Abgeschlossen → Storniert
- Mehrere Kanäle: E-Mail/SMS/Telefon/Events/Soziale Medien
- Teilnehmer-Tracking, Konversionsraten-Statistik
- Budget-vs.-Ist-Ausgabenvergleich

### 8.4 Service-Tickets
- Ticketverwaltung: Ausstehend → In Bearbeitung → Gelöst → Geschlossen
- Priorität: Niedrig/Mittel/Hoch/Dringend
- Kategorien: Technischer Support/Beschwerde/Beratung/Umtausch-Retoure/Sonstige
- Zuordnung eines Bearbeiters + Antworten (öffentlich/intern als Notiz)
- Lösungsraten-Statistik

### 8.5 Kundenanalyse-Berichte
- 6 Kernkennzahlen: Neukunden/Aktive Kunden/Bindungsrate/Durchschnittlicher Auftragswert/CLV/Ticket-Lösungsrate
- Automatische Berichtserzeugung (JSON-Datensnapshot)
- Unterstützt monatlich/quartalsweise/jährlich

### 8.6 Verkaufsfunnel
- Phasenkonfiguration: Erster Kontakt (10%) → Bedarfsbestätigung (30%) → Angebotslösung (50%) → Geschäftsverhandlung (70%) → Abschluss (100%) → Verloren (0%)
- Chancen: Kunde, aktuelle Phase, geschätzter Betrag, Abschlusswahrscheinlichkeit, voraussichtliches Abschlussdatum, Verantwortlicher
- Chancenstatus: Verloren/Laufend/Abgeschlossen
- Phasenwechsel-Tracking

### 8.7 Kunden-Public-Pool
- Kunden-Public-Pool: Kunden ohne zugewiesene Zuständigkeit oder mit abgelaufener Follow-up-Frist gelangen automatisch in den Public-Pool
- Rückholregeln: Rückholtage ohne Follow-up werden pro Kundenstufe festgelegt
- Begrenzung der maximalen Anzahl pro Person, um eine Anreicherung von Kundenressourcen zu verhindern
- Aufnehmen/Freigeben/Rückholen erzeugen jeweils Buchungsaufzeichnungen
- Fördert die Aktivität des Vertriebsteams und verhindert das Verkümmern von Kunden

### 8.8 CRM-Angebotsverwaltung
- CRM-interner Angebotsprozess, unabhängig vom Vertriebsmodul
- Status: Entwurf → Gesendet → Kundenbestätigung → In Vertrag umgewandelt → Ungültig
- Angebotsgültigkeit wird unterstützt
- Direkte Umwandlung in einen Vertrag wird unterstützt (`to-contract`)
- Verknüpft Kunde und Chance

### 8.9 Vertragsverwaltung
- Vollständiger Lebenszyklus des Vertrags: Entwurf → Ausstehende Genehmigung → Genehmigt → In Ausführung → Abgeschlossen/Beendet
- Verknüpft Kunde, Chance, Angebot
- Vertragspositionen: Artikel/Menge/Stückpreis/Betrag
- Erfasst Unterzeichnungsdatum, Start-/Enddatum
- Vertragsklausel-Inhalt (TEXT-Großfeld)
- Verantwortlicher-Zuordnung

---

## 9. Genehmigungsworkflow-Modul

### 9.1 Workflow-Definition
- Workflow-Name, Beschreibung, anwendbares Modul
- Konfiguration mehrstufiger Genehmigungsketten
- Jeder Knoten bestimmt Genehmiger/Genehmigungsrolle, Genehmigungsstrategie (Gegenzeichnung/Alternativzeichnung)

### 9.2 Genehmigungsprozess
- Einreichen des Geschäftsbelegs zur Genehmigung → automatische Erstellung der Genehmigungsinstanz
- Ablauf entlang der vordefinierten Knoten, jeder Knoten wird von den Genehmigern der Reihe nach bearbeitet
- Genehmigungsoperationen: Einreichen (aus dem Geschäftsmodul heraus), Genehmigen, Ablehnen, Zurückziehen
- Genehmigungsergebnis ruft das Geschäftsmodul auf und aktualisiert den Belegstatus
- Meine Genehmigungsliste: ausstehend/erledigt

### 9.3 Genehmigungsaufzeichnungen
- Vollständige Nachverfolgung der Genehmigungskette: jeder Schritt erfasst Genehmiger, Operation, Stellungnahme, Zeitpunkt
- Genehmigungsinstanz verknüpft mit der Geschäftsbelegnummer

---

## 10. Nachrichtenmodul

### 10.1 Benachrichtigungsverwaltung
- Benachrichtigungsliste: nach Zeit absteigend, paginiert
- Benachrichtigungstypen: Genehmigungsbenachrichtigung, Systemankündigung, Geschäftswarnung
- Als gelesen markieren: einzeln / alle gelesen
- Ungelesen-Zähler: Echtzeit-Anzahl ungelesener Nachrichten

### 10.2 Benachrichtigungsvorlagen
- Vordefinierte Benachrichtigungsvorlagen (Titel + Inhalts-Platzhalter)
- Vorlagenkategorien: Genehmigung/Warnung/System
- Benachrichtigungseinstellungen: Kanalpräferenzen pro Benutzer

### 10.3 Benachrichtigungsservice
- NotificationService mit einheitlicher Versand-Schnittstelle
- Mehrkanal-Erweiterung wird unterstützt (In-System-Nachricht/E-Mail/SMS/WebSocket)

---

## 11. Projektmanagement-Modul

### 11.1 Projektverwaltung
- Projekt-CRUD: Name, Beschreibung, Status, Start-/Enddatum, Verantwortlicher
- Projektstatus: In Planung → Laufend → Abgeschlossen → Archiviert
- Projektmitgliederverwaltung: Hinzufügen/Entfernen von Projektmitgliedern

### 11.2 Aufgabenverwaltung
- Aufgaben-CRUD: Titel, Beschreibung, Priorität, Status, Fälligkeitsdatum
- Verknüpft Projekt, unterstützt Eltern-/Kinderaufgaben
- Aufgabenstatus: Ausstehend → Laufend → Abgeschlossen → Geschlossen
- Aufgabenverteilung: Bestimmung des Verantwortlichen

### 11.3 Zeiterfassung
- Arbeitszeiterfassung pro Aufgabe: Datum, Dauer, Beschreibung
- Arbeitszeitstatistik nach Projekt zusammengefasst

---

## 12. Personalmodul

### 12.1 Organisationsstruktur
- Abteilungsverwaltung: Baumstruktur, Abteilungsname, Code, Verantwortlicher, Elternabteilung
- Positionsverwaltung: Positionsname, Code, zugehörige Abteilung, Status

### 12.2 Mitarbeiterverwaltung
- Mitarbeiterstamm: Code, Name, Geschlecht, Handynummer (verschlüsselt), E-Mail (verschlüsselt), Eintrittsdatum, Abteilung, Position
- Status: Aktiv/Ausgeschieden
- Verknüpft Systembenutzerkonto

### 12.3 Anwesenheitsverwaltung
- Stempeln: Arbeitsbeginn-Stempel, Arbeitsende-Stempel, mit Zeiterfassung
- Anwesenheitsabfrage: nach Mitarbeiter + Datumsbereich
- Anwesenheitsregeln: Arbeitszeit, Schwellenwerte für Verspätung/frühes Gehen

### 12.4 Urlaubsverwaltung
- Urlaubs-CRUD: Typ (Sonderurlaub/Krankheitsurlaub/Jahresurlaub usw.), Start-/Endzeit, Grund
- Genehmigungsprozess: Einreichen → Genehmigung durch Abteilungsleiter → Genehmigen/Ablehnen
- Status: Ausstehende Genehmigung → Genehmigt → Abgelehnt

### 12.5 Gehaltsverwaltung
- Gehaltsposten: Grundgehalt/Leistung/Zulagen/Abzugsposten usw., Berechnungsweise
- Gehaltszahlung: monatliche Erzeugung der Gehaltsbelege, verknüpft Mitarbeiter
- Zahlungsstatus: Ausstehende Auszahlung → Ausgezahlt

---

## 13. Produktions- und Fertigungsmodul

### 13.1 BOM (Stückliste)
- BOM-Definition: Elternprodukt, Kindermaterialien, Standardverbrauch, Einheit, Arbeitsschritt
- BOM-Ebenen: mehrstufige BOM-Auflösung wird unterstützt
- Versionsverwaltung: BOM-Änderungsaufzeichnungen

### 13.2 Produktionsaufträge
- Produktionsauftrags-CRUD: Produkt, Planmenge, geplantes Start-/Enddatum
- Status: Ausstehende Produktion → In Produktion → Fertiggestellt → Geschlossen
- Start-/Fertigstellungsoperationen: erfasst tatsächliche Start-/Endzeit
- Produktionspositionen: Materialentnahmeliste (auf Basis der BOM-Auflösung)

### 13.3 Arbeitspläne
- Arbeitsplandefinition: Produkt, Arbeitsschrittfolge, Standardarbeitszeit je Arbeitsschritt
- Verknüpft BOM und Workstation

### 13.4 Workstations
- Workstation-CRUD: Name, Code, Typ, Kapazität, Status
- Verknüpft Arbeitsschritte des Arbeitsplans

### 13.5 MRP (Materialbedarfsplanung)
- MRP-Plan: Materialbedarfsberechnung auf Basis von Verkaufsaufträgen/Produktionsplänen + BOM
- Automatische Erzeugung von Einkaufsvorschlägen (bei unzureichenden Rohstoffen) und Produktionsvorschlägen (bei unzureichenden Halbfabrikaten)
- MRP-Positionen: Material, Bruttobedarf, verfügbare Bestandsmenge, Nettobedarf, vorgeschlagene Auftragsmenge
- Planstatus: Entwurf → Erzeugt → Einkaufs-/Produktionsvorschläge erteilt

---

## 14. Benutzerdefiniertes Berichtsmodul

### 14.1 Berichtsdefinition
- Berichtsvorlagen-CRUD: Name, Beschreibung, Datensatz, Felder, Filterbedingungen, Diagrammtyp
- Datensätze: vordefinierte SQL-Abfragen oder Modellmethoden
- Berichtsfelder: Spaltenname, Anzeigename, Datentyp, Sortierung
- Filter: Feld, Operator, Standardwert

### 14.2 Berichtsausführung
- Ausführen erzeugt Berichtsdaten: Filterbedingungen, Sortierung, Paginierung werden angewendet
- Ergebnisdarstellung: Tabelle oder Diagramm (über Frontend gerendert)
- Export wird unterstützt

### 14.3 Zeitgesteuerte Planung
- Berichts-Planungsaufgaben: Bericht bestimmen, Ausführungsfrequenz (cron), Empfänger
- Planungsstatus: Aktiviert/Deaktiviert
- Ausführungsverlauf-Abfrage

---

## 15. Dashboards

### 15.1 Geschäftsübersicht
- Verkaufs-/Einkaufsbeträge heute/diesen Monat
- Forderungs-/Verbindlichkeitsgesamtbeträge, Lagerwert, Rohertrag
- Daten-Redis-Cache 5 Minuten

### 15.2 Vertriebs-Dashboard
- Verkaufstrend, Kunden-Ranking Top10
- CRM-Funnel-Konversionsanalyse

### 15.3 Bestands-Dashboard
- Lagerwert, Warnstatistik
- Ein-/Ausgangstrend (nach Tag/Richtung)

### 15.4 Finanz-Dashboard
- Forderungs-/Verbindlichkeitsgesamtbeträge, Zahlungseingänge/-ausgänge des Monats
- Zusammenfassung der Kassen- und Banksalden

---

## 16. Internationalisierung (i18n)

### 16.1 Automatische Spracherkennung
- Automatische Erkennung über den Request-Header `Accept-Language` (zh-CN → Chinesisch, en → Englisch)
- Die Locale-Middleware wird als erste der globalen Middleware-Kette ausgeführt
- Fallback-Kette: aktuelle Sprache → konfigurierte fallback_locale → Rückgabe des Original-Keys

### 16.2 Übersetzungsdateien
- Verzeichnis: `resource/translations/{locale}/`
- Allgemeine Nachrichten: `common.php` (41 Keys: Erfolg/Fehler/Erstellen/Aktualisieren/Löschen/Validierung usw.)
- Modulnamen: `modules.php` (69 Keys: Artikel/Einkauf/Vertrieb/Bestand/Finanzen/CRM usw.)
- Validierungsregeln: `validation.php` (11 Regeln + 10 Feld-Labels)

### 16.3 Verwendungsweise
- Im Controller: `$this->trans('created')`
- Globale Funktionen: `__('modules.product')`, `__m('finance')`
- Modulnamen: `__('modules.product')` → Artikel / Product

---

## 17. Exportfunktionen

### 17.1 Excel-Export
- Alle Listen unterstützen ?export=excel
- PhpSpreadsheet erzeugt .xlsx
- Kopfzeile blau mit weißer Schrift + eingefrorene erste Zeile + automatische Spaltenbreite
- Sensible Felder werden automatisch maskiert

### 17.2 PDF-Export
- Dashboard-Datenpanels unterstützen ?export=pdf
- Dompdf-Rendering, A4 quer
- Nicht entfernbare Copyright-Informationen
