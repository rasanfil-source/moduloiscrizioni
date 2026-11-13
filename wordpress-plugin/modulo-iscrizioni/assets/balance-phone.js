'use strict';
window.MIBalancePhone = function verifyPhoneDialog(challenge,cognome,nome,signal) {
  const previous=document.activeElement;
  const dialog=document.createElement('dialog');dialog.className='balance-phone-dialog';
  dialog.innerHTML='<form><h2>Un piccolo controllo prima di proseguire</h2><p>Nell’iscrizione è stato indicato questo numero: <strong data-phone-mask></strong></p><label>Inserisci le ultime quattro cifre per visualizzare la prenotazione e calcolare il saldo.<input name="digits" inputmode="numeric" autocomplete="off" pattern="[0-9]{4}" maxlength="4" required aria-label="Ultime quattro cifre del telefono"></label><p role="status" data-phone-error></p><div><button type="button" data-phone-cancel>Annulla</button><button type="submit">Prosegui</button></div></form>';
  dialog.querySelector('[data-phone-mask]').textContent=challenge.phone_masked;
  document.body.append(dialog);
  return new Promise(resolve=>{
    let finished=false;
    const finish=value=>{if(finished)return;finished=true;signal?.removeEventListener('abort',abort);dialog.remove();previous?.focus();resolve(value);};
    const abort=()=>finish(null);signal?.addEventListener('abort',abort,{once:true});
    dialog.addEventListener('cancel',e=>{e.preventDefault();finish(null);});dialog.querySelector('[data-phone-cancel]').onclick=()=>finish(null);
    dialog.querySelector('form').onsubmit=async e=>{
      e.preventDefault();const button=dialog.querySelector('[type=submit]');button.disabled=true;
      try{
        const result=await gasPost({action:'verifyPhone',cognome,nome:challenge.nome||nome,candidate:challenge.row,challenge:challenge.challenge,digits:dialog.querySelector('[name=digits]').value},signal);
        if(result.success)finish(result);
        else {dialog.querySelector('[data-phone-error]').textContent=result.message||result.error;dialog.querySelector('[name=digits]').select();}
      }catch(error){if(error.name==='AbortError')finish(null);else dialog.querySelector('[data-phone-error]').textContent='Verifica non disponibile. Riprova.';}
      finally{button.disabled=false;}
    };
    dialog.showModal();dialog.querySelector('[name=digits]').focus();
  });
};
