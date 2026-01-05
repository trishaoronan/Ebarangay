(function(){
  'use strict';

  function sanitizeDigits(val){ return String(val||'').replace(/\D+/g,''); }
  function formatPH(digits){
    if(!digits) return '';
    var d = digits;
    var parts = [];
    if(d.length <= 4){ parts.push(d); }
    else if(d.length <= 7){ parts.push(d.slice(0,4)); parts.push(d.slice(4)); }
    else{ parts.push(d.slice(0,4)); parts.push(d.slice(4,7)); parts.push(d.slice(7,11)); }
    return parts.join('-');
  }

  function isValidPHMobile(val){ return /^09\d{9}$/.test(val); }

  function wireFormatter(input){
    if(!input) return;
    // avoid wiring twice
    if(input.__contactFormatterWired) return;
    input.__contactFormatterWired = true;

    var errorEl = null;
    // try to find nearby .invalid-feedback
    var next = input.nextElementSibling;
    if(next && next.classList && next.classList.contains('invalid-feedback')) errorEl = next;

    var hideTimer = null;
    function showTransient(msg){
      if(hideTimer) clearTimeout(hideTimer);
      if(errorEl){ errorEl.textContent = msg; errorEl.style.display = 'block'; }
      input.classList.add('is-invalid');
      hideTimer = setTimeout(function(){ if(errorEl) errorEl.style.display = 'none'; input.classList.remove('is-invalid'); }, 1200);
    }

    function sanitize(){
      var before = input.value;
      var cleanedRaw = sanitizeDigits(before);
      var cleaned = cleanedRaw.slice(0,11);
      var formatted = formatPH(cleaned);
      if(before !== formatted){
        input.value = formatted;
        if(cleanedRaw !== sanitizeDigits(before)) showTransient('Digits only (0-9).');
      }
    }

    input.addEventListener('input', sanitize);
    input.addEventListener('paste', function(){ setTimeout(sanitize,0); });
    input.addEventListener('blur', function(){
      sanitize();
      var v = sanitizeDigits(input.value);
      input.value = formatPH(v);
      if(v && !isValidPHMobile(v)){
        if(errorEl){ errorEl.textContent = 'Enter a valid 11-digit number starting with 09.'; errorEl.style.display = 'block'; }
        input.classList.add('is-invalid');
      } else {
        if(errorEl) errorEl.style.display = 'none';
        input.classList.remove('is-invalid');
      }
    });
  }

  // find all tel inputs and wire
  document.addEventListener('DOMContentLoaded', function(){
    var inputs = Array.prototype.slice.call(document.querySelectorAll('input[type="tel"]'));
    inputs.forEach(function(inp){ wireFormatter(inp); });
  });
})();
