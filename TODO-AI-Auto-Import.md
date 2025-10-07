# TODO - AI Auto Import Plugin

## 🔴 Kritieke Prioriteit (Nu doen)

- [ ] **Test RDW API integratie volledig**
  - [ ] Controleer of alle velden correct worden opgehaald
  - [ ] Test met minimaal 10 verschillende kentekens
  - [ ] Documenteer welke velden altijd aanwezig zijn en welke optioneel

- [ ] **Implementeer `generate_car_description()` functie**
  - [ ] Gebruik AI (OpenAI/Claude API) om aantrekkelijke beschrijvingen te genereren
  - [ ] Fallback naar template-based beschrijvingen als AI niet beschikbaar is
  - [ ] Include specificaties uit RDW data

- [ ] **Database.php class aanmaken**
  - [ ] Create tables functie implementeren
  - [ ] Schema voor import history
  - [ ] Schema voor AI-gegenereerde content cache

## 🟠 Hoge Prioriteit (Deze week)

- [ ] **Plugin.php class implementeren**
  - [ ] Init functie met alle hooks
  - [ ] Admin menu structuur
  - [ ] Settings page

- [ ] **Bulk import functionaliteit**
  - [ ] CSV upload met kentekens
  - [ ] Batch processing (max 5-10 per keer om API limits te respecteren)
  - [ ] Progress indicator
  - [ ] Error handling per kenteken

- [ ] **Image upload en kenteken herkenning**
  - [ ] Google Vision API integratie testen
  - [ ] Upload interface in admin
  - [ ] Fallback naar handmatige invoer

- [ ] **Post type 'listings' validatie**
  - [ ] Controleer of custom post type 'listings' bestaat
  - [ ] Maak optie om post type te selecteren in settings
  - [ ] Documenteer welke custom fields verwacht worden

## 🟡 Gemiddelde Prioriteit (Deze maand)

- [ ] **Settings pagina**
  - [ ] RDW API configuratie (indien API key nodig in toekomst)
  - [ ] Google Vision API credentials upload
  - [ ] AI provider keuze (OpenAI/Claude/Custom)
  - [ ] Default post status (draft/publish)
  - [ ] Custom field mapping

- [ ] **Auto-enrichment met extra data**
  - [ ] Specs uit andere RDW datasets (gebreken, APK, etc.)
  - [ ] Marktwaarde schatting via externe APIs
  - [ ] Vergelijkbare auto's zoeken

- [ ] **Duplicate detection**
  - [ ] Check of kenteken al bestaat
  - [ ] Optie om bestaande posts te updaten
  - [ ] Merge functionaliteit

- [ ] **Foto's automatisch ophalen**
  - [ ] Integratie met stock foto APIs
  - [ ] Merk/model gebaseerde foto search
  - [ ] Featured image automatisch instellen

## 🟢 Lage Prioriteit (Toekomst)

- [ ] **Scheduling/Cron jobs**
  - [ ] Automatische imports op vaste tijden
  - [ ] Re-import om data up-to-date te houden
  - [ ] Email notificaties bij nieuwe imports

- [ ] **Rapportage dashboard**
  - [ ] Statistieken over imports
  - [ ] Meest geïmporteerde merken
  - [ ] Foutmeldingen overzicht

- [ ] **Multi-language ondersteuning**
  - [ ] Vertaalbare strings
  - [ ] .pot file genereren
  - [ ] NL en EN vertalingen

- [ ] **Export functionaliteit**
  - [ ] Export naar CSV
  - [ ] Export naar XML
  - [ ] Backup van import history

- [ ] **Premium features**
  - [ ] Geavanceerde AI beschrijvingen met SEO optimalisatie
  - [ ] Automatische prijssuggesties
  - [ ] Integratie met populaire automotive themes

## 🔧 Technische Verbeteringen

- [ ] **Error handling verbeteren**
  - [ ] Custom exception classes
  - [ ] Structured logging
  - [ ] Admin notification system

- [ ] **Performance optimalisatie**
  - [ ] Caching van API responses
  - [ ] Transients gebruiken voor veelgebruikte data
  - [ ] Database queries optimaliseren

- [ ] **Security hardening**
  - [ ] Nonce verificatie overal
  - [ ] Capability checks
  - [ ] Input sanitization audit
  - [ ] SQL injection prevention

- [ ] **Code organisatie**
  - [ ] Volledige class-based architectuur
  - [ ] Dependency injection
  - [ ] Unit tests schrijven
  - [ ] PHPDoc comments

## 📚 Documentatie

- [ ] **README.md aanmaken**
  - [ ] Installatie instructies
  - [ ] Feature lijst
  - [ ] Screenshots
  - [ ] FAQ

- [ ] **Developer documentatie**
  - [ ] Hooks en filters lijst
  - [ ] API documentatie
  - [ ] Code examples

- [ ] **User manual**
  - [ ] Stap-voor-stap handleiding
  - [ ] Video tutorials
  - [ ] Troubleshooting guide

## 🐛 Bekende Issues

- [ ] **Google Vision API credentials path**
  - Momenteel: `plugin_dir_path(__FILE__) . '../credentials.json'`
  - TODO: Verplaats naar veilige locatie buiten web root
  - Of: Gebruik WordPress options voor credentials

- [ ] **Post type 'listings' hardcoded**
  - Moet configureerbaar zijn
  - Compatibiliteit met verschillende themes checken

- [ ] **Geen rate limiting voor RDW API**
  - Implementeer throttling
  - Documenteer API limits

## 🎯 Milestone Planning

### v1.1.0 - Basis Functionaliteit (Week 1-2)
- Kritieke prioriteit items
- Database implementatie
- Plugin class structuur

### v1.2.0 - Bulk Import (Week 3-4)
- CSV upload
- Batch processing
- Progress tracking

### v1.3.0 - AI Enhancement (Week 5-6)
- AI beschrijvingen
- Auto-enrichment
- Foto's ophalen

### v2.0.0 - Pro Features (Maand 2-3)
- Scheduling
- Advanced reporting
- Premium features

## 📝 Notities

- **RDW API documentatie**: https://opendata.rdw.nl/
- **Available datasets**: 
  - Kenteken: m9d7-ebf2
  - Gebreken: a34c-vvps
  - APK: hx2c-gt7k

- **Overleg met Pieter nodig over**:
  - Welke AI provider gebruiken?
  - Budget voor API calls?
  - Gewenste posting frequency?
  - Custom theme/plugin requirements?

---
**Laatst bijgewerkt:** 7 oktober 2025
**Versie:** 1.0.1
