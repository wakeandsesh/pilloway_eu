document.addEventListener('DOMContentLoaded', function () {
 'use strict';
 var banner=document.getElementById('pilloway-consent-banner'); if(!banner)return;
 var cfg=window.PillowayConsent||{}, name=cfg.cookie||'pilloway_consent';
 var reopen=document.createElement('button'); reopen.type='button'; reopen.className='pilloway-consent-reopen'; reopen.textContent=(cfg.strings||{}).reopen||'Cookies'; document.body.appendChild(reopen);
 function choice(){var entry=document.cookie.split('; ').find(function(v){return v.startsWith(name+'=');}); return entry ? entry.substring(name.length+1):'';}
 function show(){banner.hidden=false;reopen.hidden=true;}
 function hide(){banner.hidden=true;reopen.hidden=false;}
 function save(value){document.cookie=name+'='+value+';path=/;max-age=31536000;SameSite=Lax'+(location.protocol==='https:'?';Secure':'');hide();window.dispatchEvent(new CustomEvent('pilloway:consent',{detail:{choice:value}}));reopen.focus();}
 reopen.addEventListener('click',function(){show();banner.focus();});
 banner.addEventListener('click',function(e){var b=e.target.closest('[data-consent]');if(b)save(b.dataset.consent);});
 banner.addEventListener('keydown',function(e){if(e.key==='Escape')save('essential');});
 ['all','essential'].includes(choice())?hide():show();
});
