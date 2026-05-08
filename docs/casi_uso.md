Autenticazione & Account

UC01 — Registrazione

- Attore: Paziente
- Precondizione: L'utente non è autenticato.
- Flusso principale:
  a. Il paziente apre `/register`.
  b. Compila il form: nome, cognome, email, password + conferma, data di nascita, comune di nascita, sesso (M/F), telefono.
  c. Invia il form.
  d. Il sistema valida i dati.
  e. Il sistema crea l'utente con ruolo `patient`, crea il `PatientProfile` e calcola automaticamente il codice fiscale dai dati anagrafici.
  f. Effettua login automatico e redirige a `/patient`.
- Eccezioni: email già esistente, comune di nascita non valido, password non conforme, data di nascita non valida -> messaggio di errore nel form.

UC02 — Effettuare login

- Attori: Paziente, Medico
- Precondizione: Account esistente; utente non autenticato.
- Flusso principale:
  a. L'utente apre `/login`.
  b. Inserisce username o email e password.
  c. Il sistema verifica le credenziali e, se valide, rigenera la sessione.
  d. Redirige al portale in base al ruolo (`/patient` o `/doctor`); se il ruolo non è supportato -> `/unsupported-role`.
- Eccezioni: credenziali non valide -> "Credenziali non valide.".

UC03 — Effettuare logout

- Attori: Paziente, Medico
- Precondizione: Utente autenticato.
- Flusso principale:
  a. L'utente clicca il tasto logout.
  b. Il sistema chiude la sessione e redirige alla login page.

UC04 — Modifica password

- Attori: Paziente, Medico
- Precondizione: Utente autenticato.
- Flusso principale:
  a. L'utente apre la sezione profilo.
  b. Inserisce password attuale, nuova password e conferma.
  c. Il sistema verifica la password attuale e, se corretta, salva la nuova password.

UC05 — Eliminare account

- Attore: Paziente
- Precondizione: Utente autenticato.
- Flusso principale:
  a. Il paziente apre la sezione profilo.
  b. Inserisce la password attuale e clicca elimina account.
  c. Il sistema verifica la password attuale e chiede conferma dell'eliminazione.
  d. Il paziente conferma.
  e. Il sistema elimina il profilo del paziente e le sue prenotazioni.

Area Paziente

UC06 — Visualizzare dashboard paziente

- Attore: Paziente
- Precondizione: Login con ruolo `patient`.
- Flusso principale:
  a. Il paziente apre la dashboard paziente.
  b. Il sistema recupera tutti i suoi appuntamenti, separa quelli futuri attivi da quelli passati o cancellati ed evidenzia il prossimo appuntamento.

UC07 — Aggiornare info paziente

- Attore: Paziente
- Precondizione: Login con ruolo `patient`.
- Flusso principale:
  a. Il paziente apre la sezione profilo.
  b. Modifica le informazioni consentite.
  c. Il sistema valida e salva i dati su `User` e `PatientProfile`.
- Nota: i dati anagrafici fissi sono in sola lettura.

UC08 — Avviare prenotazione guidata

- Attore: Paziente
- Precondizione: Login con ruolo `patient`; esistono prestazioni attive e regole di disponibilita future.
- Flusso principale:
  a. Il paziente apre la sezione di prenotazione.
  b. Sceglie una prestazione attiva.
  c. Sceglie un giorno e un orario generato dinamicamente dalle regole di disponibilita.
  d. Aggiunge eventuali note.
  e. Conferma.
  f. Il sistema, in transazione, blocca il profilo medico, rigenera la disponibilita per l'orario richiesto, valida che non sia passato, chiuso o sovrapposto ad appuntamenti attivi, crea l'`Appointment` con stato `confirmed`.
  g. Redirige alla sezione appuntamenti con messaggio "Appuntamento confermato.".
- Eccezioni: orario non piu disponibile o prestazione disattivata -> errore di validazione.

UC09 — Modificare appuntamento

- Attore: Paziente
- Precondizione: Appuntamento del paziente, con stato `confirmed` e in data futura, non entro meno di 24 ore.
- Flusso principale:
  a. Il paziente apre `/appointments/{id}/edit`.
  b. Visualizza le disponibilita generate per la stessa prestazione, escludendo l'appuntamento attuale dal controllo sovrapposizioni.
  c. Seleziona un nuovo orario e conferma.
  d. Il sistema, in transazione, aggiorna `inizio_il` e `fine_il` dell'appuntamento dopo aver riverificato l'orario.

UC10 — Annullare appuntamento

- Attore: Paziente
- Precondizione: Appuntamento del paziente, `confirmed` e futuro.
- Flusso principale:
  a. Il paziente avvia l'annullamento.
  b. Inserisce un motivo.
  c. Il sistema, in transazione, imposta lo stato a `cancelled`; l'orario torna prenotabile se e ancora coperto dalle regole di apertura.
- Eccezioni: stato diverso da `confirmed` o appuntamento passato -> errore.

Area Medico

UC11 — Visualizzare calendario

- Attore: Medico
- Precondizione: Login con ruolo `doctor`.
- Flusso principale:
  a. Il medico apre la dashboard.
  b. Il sistema costruisce una timeline a 30 minuti che integra orari generati, chiusure e appuntamenti, evidenziando l'ora corrente.
  c. Mostra il fatturato della giornata.

UC12 — Visualizzare dettaglio appuntamento e dati paziente

- Attore: Medico
- Precondizione: Login con ruolo `doctor`; appuntamento esistente.
- Flusso principale:
  a. Dall'agenda il medico apre il dettaglio dell'appuntamento con paziente, prestazione, orario e note.

UC13 — Gestione disponibilità

- Attore: Medico
- Precondizione: Login con ruolo `doctor`.
- Flusso principale:
  a. Dal profilo il medico configura gli orari ricorrenti dell'ambulatorio per i sette giorni della settimana.
  b. Ogni giorno puo avere zero, una o piu finestre; eventuali pause pranzo sono rappresentate da finestre separate.
  c. Il salvataggio sostituisce le righe `working_hours` attive e valide per sempre, salvo conflitti con appuntamenti futuri attivi.
  d. Dall'agenda il medico gestisce solo eventi non ricorrenti: `closures` per ferie/blocchi/chiusure e `special_openings` per aperture extra.

UC14 — Gestione tipi di visita

- Attore: Medico
- Precondizione: Login con ruolo `doctor`.
- Flusso principale:
  a. Il medico apre la pagina trattamenti.
  b. Crea una prestazione: nome univoco, categoria `visit` o `exam`, prezzo opzionale.
  c. Modifica una prestazione esistente.
  d. Disattiva una prestazione (`DELETE` -> `is_active = false`); non viene cancellata fisicamente per preservare lo storico degli appuntamenti.
