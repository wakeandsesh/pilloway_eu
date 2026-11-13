document.addEventListener('DOMContentLoaded', function () {
 'use strict';
 var banner=document.getElementById('pilloway-consent-banner'); if(!banner)return;
 var cfg=window.PillowayConsent||{}, name=cfg.cookie||'pilloway_consent';
 function choice(){var entry=document.cookie.split('; ').find(function(v){return v.startsWith(name+'=');}); return entry ? entry.substring(name.length+1):'';}
 function save(value){document.cookie=name+'='+value+';path=/;max-age=31536000;SameSite=Lax'+(location.protocol==='https:'?';Secure':'');banner.hidden=true;window.dispatchEvent(new CustomEvent('pilloway:consent',{detail:{choice:value}}));}
 banner.addEventListener('click',function(e){var b=e.target.closest('[data-consent]');if(b)save(b.dataset.consent);});
 banner.addEventListener('keydown',function(e){if(e.key==='Escape')save('essential');});
 banner.hidden=['all','essential'].includes(choice());
});
