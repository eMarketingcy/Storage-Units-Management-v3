(function(){
  function toggleRegion(chk, region, requiredSelector){
    if(!chk || !region) return;
    var on = !!chk.checked;
    region.classList.toggle('sum-hidden', !on);
    chk.setAttribute('aria-expanded', on ? 'true' : 'false');

    if(on){
      region.style.animation = 'fadeInUp 0.3s ease';
    }

    if(requiredSelector){
      region.querySelectorAll(requiredSelector).forEach(function(el){
        if(on) { el.setAttribute('required','required'); }
        else   { el.removeAttribute('required'); }
      });
    }
  }

  function showNotification(message, type){
    var notification = document.createElement('div');
    notification.className = 'sum-notification sum-notification-' + (type || 'info');
    notification.textContent = message;
    notification.style.cssText = 'position:fixed;top:20px;right:20px;padding:16px 24px;background:white;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.15);z-index:10000;animation:slideInRight 0.3s ease;';
    if(type === 'error') notification.style.background = '#fee2e2';
    if(type === 'success') notification.style.background = '#d1fae5';
    document.body.appendChild(notification);
    setTimeout(function(){ notification.remove(); }, 4000);
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
          var fileName = input.files && input.files.length ? input.files[0].name : 'Choose file…';
          var fileSize = input.files && input.files.length ? ' (' + (input.files[0].size / 1024 / 1024).toFixed(2) + ' MB)' : '';
          hint.textContent = fileName + fileSize;
          hint.style.color = input.files && input.files.length ? '#f97316' : '';
          hint.style.fontWeight = input.files && input.files.length ? '600' : '';
        }
      });
    });

    // Form validation with visual feedback
    var form = document.querySelector('.sum-form');
    if(form){
      form.addEventListener('submit', function(e){
        var invalid = form.querySelector(':invalid');
        if(invalid){
          e.preventDefault();
          invalid.scrollIntoView({behavior:'smooth', block:'center'});
          invalid.focus();
          showNotification('Please fill in all required fields', 'error');

          // Highlight invalid field
          invalid.style.animation = 'shake 0.5s';
          setTimeout(function(){ invalid.style.animation = ''; }, 500);
        } else {
          // Show loading state
          var submitBtn = form.querySelector('.sum-btn');
          if(submitBtn){
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            submitBtn.style.opacity = '0.7';
          }
        }
      }, {passive:false});

      // Real-time validation feedback
      form.querySelectorAll('input[required], select[required], textarea[required]').forEach(function(field){
        field.addEventListener('blur', function(){
          if(!field.validity.valid){
            field.style.borderColor = '#ef4444';
          } else {
            field.style.borderColor = '#10b981';
          }
        });

        field.addEventListener('input', function(){
          if(field.validity.valid && field.style.borderColor === 'rgb(239, 68, 68)'){
            field.style.borderColor = '#10b981';
          }
        });
      });
    }

    // Smooth scroll for section navigation
    document.querySelectorAll('.sum-section').forEach(function(section){
      section.style.scrollMarginTop = '20px';
    });

    // Add progress indicator
    if(form){
      var sections = form.querySelectorAll('.sum-section');
      var observer = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if(entry.isIntersecting){
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
          }
        });
      }, {threshold: 0.1});

      sections.forEach(function(section){
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';
        section.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(section);
      });
    }
  });

  // Add CSS for shake animation
  var style = document.createElement('style');
  style.textContent = '@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-10px); } 75% { transform: translateX(10px); } } @keyframes slideInRight { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }';
  document.head.appendChild(style);
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
