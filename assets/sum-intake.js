(function(){
  function toggleRegion(chk, region, requiredSelector){
    if(!chk || !region) return;
    var on = !!chk.checked;
    region.classList.toggle('sum-hidden', !on);
    chk.setAttribute('aria-expanded', on ? 'true' : 'false');

    if(requiredSelector){
      region.querySelectorAll(requiredSelector).forEach(function(el){
        if(on) { el.setAttribute('required','required'); }
        else   { el.removeAttribute('required'); }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    // Company toggle
    var companyChk = document.getElementById('has_company');
    var companyBlk = document.getElementById('business_block');
    if(companyChk){
      companyChk.addEventListener('change', function(){
        toggleRegion(companyChk, companyBlk, 'input,select,textarea');
      });
      toggleRegion(companyChk, companyBlk, 'input,select,textarea');
    }

    // Alt contact toggle
    var altChk = document.getElementById('has_alt_contact');
    var altBlk = document.getElementById('alt_block');
    if(altChk){
      altChk.addEventListener('change', function(){
        toggleRegion(altChk, altBlk, 'input');
      });
      toggleRegion(altChk, altBlk, 'input');
    }

    // File input hint (show filename)
    document.querySelectorAll('.sum-file').forEach(function(input){
      input.addEventListener('change', function(){
        var hint = input.parentElement.querySelector('.sum-file-hint');
        if(hint){
          hint.textContent = input.files && input.files.length ? input.files[0].name : 'Choose file…';
        }
      });
    });

    // Simple client-side required check styling
    var form = document.querySelector('.sum-form');
    if(form){
      form.addEventListener('submit', function(e){
        var invalid = form.querySelector(':invalid');
        if(invalid){
          // scroll into view for mobile
          invalid.scrollIntoView({behavior:'smooth', block:'center'});
        }
      }, {passive:true});
    }
  });
})();

(function(){
  function postWithRest(form){
    if (!window.sumIntake || !sumIntake.restUrl) return false;
    var fd = new FormData(form);
    return fetch(sumIntake.restUrl, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin'
    }).then(function(r){ return r.json(); })
      .then(function(data){
        if (data && data.ok) {
          window.location.href = data.redirect || (window.location.href + (window.location.search ? '&':'?') + 'sum_submitted=1');
          return true;
        }
        if (data && data.error) alert(data.error);
        return false;
      }).catch(function(){
        return false;
      });
  }

  document.addEventListener('DOMContentLoaded', function(){
    var form = document.querySelector('.sum-form');
    if (!form) return;

    form.addEventListener('submit', function(e){
      // let native HTML5 validation run first
      if (!form.checkValidity()) return;
      e.preventDefault();
      postWithRest(form).then(function(ok){
        if (!ok) form.submit(); // fallback to admin-post if REST failed
      });
    });
  });
})();
