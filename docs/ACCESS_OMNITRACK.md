# Cosa controlla l'app su Access (OmniTrack)

Riepilogo di come l'app Inventario interroga il database Access di OmniTrack quando un operatore scansiona o digita un codice.

- **Database:** quello indicato da `ACCESS_DSN` nel file `.env`, per esempio `Z:\OMNITRACK1.3_be.accdb`, raggiunto via ODBC.
- **Codice:** tutta la logica è in `app/Services/ArticleLookupService.php`.
- **Regola generale:** Access viene interrogato **solo se il codice non è stato trovato prima in Esolver**. Su Esolver si cerca il lotto in `MagProgrLotto`, campo `RifLottoAlfab`.

---

## 1. Lotto letto con barcode, a mano o dal QR OmniTrack

L'app prende la parte del lotto **prima del primo separatore** e la usa come ID (funzione `parseLot`).

| Il lotto contiene | Esempio | Tabella Access | Campo cercato | Campi letti |
|---|---|---|---|---|
| `.` → **ingrediente** | `5789.14026.734` → ID **5789** | `T_INGREDIENTI` | `IDIngrediente` | `Nome comerc Ingrediente`, `CodGestionale`, `UM` |
| `-` → **prodotto** | `8055-14026280` → ID **8055** | `T_PRODOTTI` | `IDProdottoAziendale` | `Nome commerciale PA`, `CodGestionale`, `CodiceAziendale`, `unimis` |

Un lotto senza `.` né `-`, o con una delle due parti vuota, non viene cercato su Access per ID.

**Dati salvati nella registrazione**
- **Codice articolo:** `CodGestionale`. Per i prodotti, se è vuoto si usa `CodiceAziendale` e, in mancanza anche di quello, l'ID.
- **Descrizione:** `Nome comerc Ingrediente` per gli ingredienti, `Nome commerciale PA` per i prodotti.
- **Unità di misura:** il valore numerico di `UM` (ingredienti) o `unimis` (prodotti) viene tradotto con `config/inventory.php`:

  | Valore | 1 | 2 | 3 | 4 | 5 | 6 | altro |
  |---|---|---|---|---|---|---|---|
  | UM | PZ | KG | KG | LT | MT | GR | PZ |

- **Origine del dato (`db_source`):** `access`.

### ⚠️ Il lotto non viene verificato
Su Access l'app **non controlla che il lotto esista**. Controlla solo che esista il prodotto o l'ingrediente indicato dal numero iniziale.
- Questi record hanno `lot_match = false` e l'operatore vede l'avviso "lotto non verificato".
- Di conseguenza un lotto inventato che inizia con un ID esistente, per esempio `5789.99999`, viene accettato.

### Scadenza
La scadenza **non viene letta da Access**. Arriva dal codice scansionato:
- `…;gg/mm/aaaa` in coda al lotto, per esempio `5789.14026.734;19/11/2027`;
- oppure il campo `#15AAMMGG` del QR OmniTrack, per esempio `#15271119` = 19/11/2027.

---

## 2. Ordine delle ricerche per tipo di codice

**Barcode o inserimento manuale (senza `#`)**
1. Esolver: `MagProgrLotto.RifLottoAlfab` = lotto completo.
2. Access per ID (tabella sopra): `T_INGREDIENTI.IDIngrediente` oppure `T_PRODOTTI.IDProdottoAziendale`.
3. Il codice viene trattato come codice articolo: prima Esolver `ArtAnagrafica.CodArt`, poi Access `T_INGREDIENTI.CodGestionale` e infine `T_PRODOTTI.CodGestionale`.
4. Se non viene trovato da nessuna parte: "non trovato".

**QR OmniTrack** (`{lotto}#37{qtà}#15{AAMMGG}`, la parte prima del `#` contiene solo cifre e trattini)
1. Esolver: `MagProgrLotto.RifLottoAlfab` = lotto (la parte prima del primo `#`).
2. Access per ID (tabella sopra).
3. Se non viene trovato: "non trovato". In questo caso non si prova come codice articolo.

La quantità del campo `#37` viene ignorata: l'operatore la inserisce a mano.

**QR Esolver** (`{cifre}{CodArt}#{2 caratteri}{lotto}`, la parte prima del `#` contiene lettere)
1. Esolver: lotto più `CodArt` in `MagProgrLotto`.
2. Access, per completare descrizione e unità di misura: `T_INGREDIENTI.CodGestionale` = CodArt, se non c'è `T_PRODOTTI.CodGestionale` = CodArt.

Se il lotto non è in Esolver ma l'articolo è su Access, il record viene marcato `lot_match = true`, anche se il lotto non è stato verificato.

---

## 3. Ricerca articoli quando il codice non viene trovato

Dalla schermata di scansione l'operatore può cercare un articolo a mano (minimo 2 caratteri, al massimo 40 risultati). La ricerca è per somiglianza (`LIKE '%testo%'`) su:

| Tabella | Campi su cui cerca |
|---|---|
| `T_INGREDIENTI` | `CodGestionale`, `Nome comerc Ingrediente` |
| `T_PRODOTTI` | `CodGestionale`, `CodiceAziendale`, `Nome commerciale PA` |

---

## 4. Effetto nella sezione Rettifica Export

- Un record trovato su Access è salvato con `db_source = access`.
- Nel confronto con Esolver il suo codice va nella colonna **"Articolo OMNI"**, non in "Articolo Esolver".
- La riga viene abbinata alla giacenza Esolver solo se `CodGestionale` coincide con il codice articolo di Esolver, a parità di magazzino. Altrimenti risulta **"Solo Conta"**.

---

## 5. Se Access non risponde

Se la connessione ODBC fallisce (unità `Z:` non raggiungibile, driver mancante, ecc.), l'errore viene scritto nel log `storage/logs/laravel.log` e il codice viene trattato come "non trovato". La scansione non si blocca.
