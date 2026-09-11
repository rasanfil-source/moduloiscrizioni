

<!-- ═══════════════════════════════════════════ HTML ═══════════════════════════════════════════ -->
<div class="page">
  <h1>Saldo prenotazioni</h1><p style="text-align:center;color:#718096;"><?php echo esc_html( $config['eventTitle'] ); ?></p>

  <!-- Stepper 3 passi -->
  <nav id="stepper" class="stepper" aria-label="Stato procedura">
    <div class="step is-active" data-step="1">
      <div class="step-dot" aria-hidden="true">1</div>
      <div class="step-label">
        <div class="step-title">Trova la tua prenotazione</div>
        <div class="step-desc">Inserisci cognome</div>
      </div>
    </div>
    <div class="step-sep" aria-hidden="true"></div>
    <div class="step" data-step="2">
      <div class="step-dot" aria-hidden="true">2</div>
      <div class="step-label">
        <div class="step-title">Scegli opzioni</div>
        <div class="step-desc">Transfer e servizi</div>
      </div>
    </div>
    <div class="step-sep" aria-hidden="true"></div>
    <div class="step" data-step="3">
      <div class="step-dot" aria-hidden="true">3</div>
      <div class="step-label">
        <div class="step-title">Conferma e ricevi il riepilogo</div>
        <div class="step-desc">Invia email e paga</div>
      </div>
    </div>
  </nav>

  <div class="layout">

    <!-- COLONNA SINISTRA -->
    <div>
      <div id="personsContainer"></div>

      <button id="btnAddPerson" type="button">+ Aggiungi un'altra persona</button>

      <div id="calcBtnContainer">
        <button class="primary" id="calcBtn" type="button" style="display:none;">Conferma le tue scelte</button>
        <div id="recalcBanner" class="recalc-banner" role="status" aria-live="polite" aria-atomic="true">
          <span class="recalc-icon" aria-hidden="true">⚠️</span>
          <span class="recalc-text">Aggiorna importo per ricalcolare il totale e salvare le modifiche.</span>
        </div>
      </div>
    </div>

    <!-- COLONNA DESTRA (sidebar) -->
    <aside>
      <div id="warmupBadge" role="status" aria-live="polite">⏳ Un momento...</div>

      <!-- Shortcut calcola desktop -->
      <div id="desktopCalcShortcut">
        <button class="primary" id="calcBtnDesktop" type="button">Conferma le tue scelte</button>
        <div id="resetLink" style="display:none; text-align:center; margin-top:8px; font-size:.8rem; color:#a0aec0;">
          Vuoi ricominciare?
          <a href="#" id="btnResetAll"
             style="color:#718096; text-decoration:underline; text-decoration-style:dotted;"
             onclick="event.preventDefault(); resetAll();">Reimposta tutto</a>
        </div>
      </div>

      <!-- Stato prenotazione -->
      <div id="bookingStatus" role="status" aria-live="polite"></div>

      <h2 style="margin-top:0;">Riepilogo</h2>

      <div id="summaryBox" aria-live="polite">Inserisci cognome per iniziare</div>

      <!-- Breakdown dettaglio costi — visibile live appena caricata la prenotazione -->
      <div id="breakdownBox">
        <div style="font-size:.75rem;font-weight:600;color:#718096;text-transform:uppercase;
                    letter-spacing:.5px;margin-bottom:8px;">Dettaglio costi</div>
        <div id="costLines"></div>
        <div class="breakdown-row total"><span>Totale</span><span id="totOpzioni">—</span></div>
        <div class="breakdown-row caparra" id="rowCaparra"><span>Caparra versata</span><span id="caparraVersata">—</span></div>
        <div class="breakdown-row" id="rowCaparraDue"><span>Caparra da versare</span><span id="caparraDaVersare">—</span></div>
        <div class="breakdown-row" id="rowSaldo"><span>Saldo da versare</span><span id="quotaSaldo">—</span></div>
        <div class="breakdown-row caparra"><span>Versato (esclusi rimborsi effettuati)</span><span id="versatoEffettivo">—</span></div>
      </div>

      <!-- Saldo finale -->
      <div id="totalBox">
        <span id="totalBoxLabel">Totale da versare:</span>
        <span id="saldoDaVersare">€ —</span>
      </div>

      <!-- Email per il riepilogo — visibile appena caricata una prenotazione -->
      <div id="emailSection" style="display:none; margin-top:14px;">
        <label for="emailInput">La tua email</label>
        <input type="email" id="emailInput" placeholder="email@example.invalid"
               autocomplete="email" inputmode="email">
        <div style="font-size:.75rem; color:#718096; margin-top:4px;">
          Riceverai importi, IBAN e causale
        </div>
      </div>

      <!-- Status globale -->
      <div id="globalStatus" class="status" role="status" aria-live="polite" aria-atomic="true"></div>

      <!-- Azioni post-conferma: Paga con carta -->
      <div id="actionSection" style="display:none; margin-top:12px;">
        <button class="secondary" id="payBtn" type="button" disabled
          style="background:#e67e22; border-color:#e67e22; margin-top:0;">
          💳 Paga con carta
        </button>
      </div>

    </aside>
  </div>
</div>

<!-- ═══════════════════ TEMPLATE CARD PERSONA ═══════════════════ -->
<template id="personCardTemplate">
  <div class="person-card" data-person-index="">

    <div class="card-header">
      <span class="card-title">Trova la tua prenotazione</span>
      <div class="card-header-actions">
        <button type="button" class="btn-change" title="Modifica dati persona">✎ Cambia</button>
        <button type="button" class="btn-remove" title="Rimuovi persona" aria-label="Rimuovi questa persona">✕</button>
      </div>
    </div>

    <!-- Ricerca: Cognome + Nome (sempre visibili) + Carica -->
    <form onsubmit="return false;" autocomplete="off">
      <div class="input-row">

        <div class="field-col">
          <label class="person-cognome-label">Cognome</label>
          <input type="text" class="person-cognome"
                 placeholder="Es. Rossi, D'Angelo"
                 autocomplete="family-name"
                 autocorrect="off"
                 autocapitalize="words"
                 spellcheck="false"
                 inputmode="text">
          <div class="field-hint">Inserisci per trovare la tua prenotazione</div>
        </div>

        <div class="field-col">
          <label class="person-nome-label">
            Nome
          </label>
          <input type="text" class="person-nome"
                 placeholder="Es. Marco"
                 autocomplete="given-name"
                 autocorrect="off"
                 autocapitalize="words"
                 spellcheck="false"
                 inputmode="text"
                 aria-label="Nome partecipante"
                 enterkeyhint="search">
          <div class="field-hint" aria-hidden="true"> </div>
        </div>

        <div class="field-col">
          <label style="visibility:hidden;" aria-hidden="true">Azioni</label>
          <button type="button" class="primary btn-search-person btn-cerca">🔍 Trova</button>
          <div class="field-hint" aria-hidden="true"> </div>
        </div>

      </div>
    </form>

    <!-- Status singola card -->
    <div class="status person-status" role="status" aria-live="polite" aria-atomic="true"></div>

    <!-- Sezione dati caricati -->
    <div class="loaded-data-section">

      <div class="section-label">Dati prenotazione</div>

      <!-- Box opzioni non modificabili -->
      <div class="promemoria-bozza"></div>
      <div class="transfer-heading"><div class="section-label" style="margin-top:20px;font-size:1rem;font-weight:600;color:#2d3748;text-transform:none;">🚌 Vuoi aggiungere un trasferimento?</div>
      <div style="font-size:.85rem;color:#718096;margin-bottom:14px;">Seleziona solo i transfer che vuoi aggiungere. Il costo sarà incluso nel saldo.</div></div>
      <div class="transfer-grid"></div>
    </div><!-- /loaded-data-section -->
  </div><!-- /person-card -->
</template>

<!-- ═══════════════════ POPUP: PAGAMENTO CARTA ═══════════════════ -->
<div class="popup-overlay" id="payPopup"
     role="dialog" aria-modal="true" aria-labelledby="popupTitle" tabindex="-1">
  <div class="popup-box" role="document">
    <h3 id="popupTitle">Completa il pagamento con carta</h3>
    <p class="popup-subtitle">
      Verrai reindirizzato alla pagina sicura di pagamento della Parrocchia S. Eugenio
    </p>

    <div class="popup-field">
      <span class="popup-field-label">Importo</span>
      <span class="popup-field-value importo" id="popupImporto"></span>
      <button type="button" class="popup-btn-copy" onclick="copyPopupField('popupImporto',this)">📋</button>
    </div>
    <div class="popup-field">
      <span class="popup-field-label">Causale</span>
      <span class="popup-field-value" id="popupCausale"></span>
      <button type="button" class="popup-btn-copy" onclick="copyPopupField('popupCausale',this)">📋</button>
    </div>

    <div class="popup-actions">
      <button type="button" class="popup-btn-confirm" id="popupOpenBtn">Apri pagina pagamento →</button>
      <button type="button" class="popup-btn-dismiss" id="popupCloseBtn">Chiudi</button>
    </div>
    <p class="popup-hint">Il popup resta aperto — passa all'altro tab per incollare i dati.</p>
  </div>
</div>

<!-- ═══════════════════ POPUP: CONFERMA CUSTOM ═══════════════════ -->
<div class="popup-overlay" id="confirmModal"
     role="dialog" aria-modal="true" aria-labelledby="confirmTitle" tabindex="-1">
  <div class="popup-box" role="document" style="max-width:450px;">
    <h3 id="confirmTitle" style="margin-top:0;">Conferma prenotazione</h3>
    <div id="confirmBody" style="margin:14px 0; color:#4a5568; line-height:1.55; text-align:left;"></div>
    <div class="popup-actions" style="margin-top:20px;">
      <button type="button" class="popup-btn-confirm" id="confirmOkBtn" style="flex:1;">
        Conferma e procedi
      </button>
      <button type="button" class="popup-btn-dismiss" id="confirmCancelBtn">Annulla</button>
    </div>
  </div>
</div>

<!-- ═══════════════════ POPUP: CONFERMA EMAIL / IBAN ═══════════════════ -->
<div class="popup-overlay" id="ibanPopup"
     role="dialog" aria-modal="true" aria-labelledby="ibanTitle" tabindex="-1">
  <div class="popup-box" role="document">
    <h3 id="ibanTitle">✅ Prenotazione aggiornata</h3>
    <p id="ibanIntro" style="color:#4a5568; margin-bottom:18px; line-height:1.55;">
      Le tue scelte sono state registrate.<br>
      Tra pochi secondi riceverai una email con il riepilogo della prenotazione
      e le istruzioni per il pagamento.
    </p>

    <div id="bankDetails" style="background:#f7fafc; border-radius:8px; padding:14px; margin-bottom:16px;">
      <div style="font-size:.8rem; font-weight:600; color:#718096; text-transform:uppercase;
                  letter-spacing:.4px; margin-bottom:10px;">Coordinate per il bonifico</div>
      <div class="popup-field">
        <span class="popup-field-label">IBAN</span>
        <span class="popup-field-value" id="ibanValue"
              style="font-family:monospace; font-size:10px; letter-spacing:.2px;">
          <?php echo esc_html( $config['iban'] ); ?>
        </span>
        <button type="button" class="popup-btn-copy" onclick="copyPopupField('ibanValue',this)">📋</button>
      </div>
      <div class="popup-field">
        <span class="popup-field-label">Intestatario</span>
        <span class="popup-field-value" style="font-size:10px;"><?php echo esc_html( $config['holder'] ); ?></span>
      </div>
    </div>

    <div class="popup-actions" style="margin-top:16px;">
      <button type="button" class="popup-btn-dismiss" id="ibanCloseBtn"
              style="width:100%; background:#3182ce; color:#fff; border-color:#3182ce;
                     font-weight:600; font-size:1rem;">
        Chiudi
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════ JS ════════════════════════════════════════════ -->
