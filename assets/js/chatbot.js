(function(){
  var KEY = 'assistant_lang';
  var FLAG = 'assistant_lang_user_set';
  function getLang(){ try { return localStorage.getItem(KEY) || 'en'; } catch(e){ return 'en'; } }
  function applyToAssistant(lang){ try { if (window.OrderAssistant) { if (typeof window.OrderAssistant.setLanguage==='function'){ window.OrderAssistant.setLanguage(lang); } else if('language' in window.OrderAssistant){ window.OrderAssistant.language = lang; } } } catch(e){} }
  function setLang(lang){ if(!lang||typeof lang!=='string') return; try{ localStorage.setItem(KEY,lang); localStorage.setItem(FLAG,'1'); }catch(e){} applyToAssistant(lang); }
  try{ var stored=getLang(); var userSet=(localStorage.getItem(FLAG)==='1'); if(!userSet && stored!=='en'){ localStorage.setItem(KEY,'en'); } }catch(e){}
  window.setOrderAssistantLanguage = setLang;
  window.addEventListener('order-assistant:set-language', function(e){ var lang=(e&&e.detail&&e.detail.lang)||'en'; setLang(lang); });
  (function wait(){ var t=0,m=60; var id=setInterval(function(){ t++; if(window.OrderAssistant){ applyToAssistant(getLang()); clearInterval(id);} else if(t>=m){ clearInterval(id);} },500); })();
  try{ document.addEventListener('DOMContentLoaded', function(){ applyToAssistant(getLang()); }); }catch(e){}
  try{ window.addEventListener('load', function(){ applyToAssistant(getLang()); }); }catch(e){}
})();

(function(){
  var els={}; var state={lang:'en', manualLang:null};
  // Resolve URLs relative to dynamic BASE_URL (set in header.php)
  var BASE=(window.BASE_URL||'').replace(/\/+$/,'');
  function url(p){ return BASE + p; }
  function ensureUI(){ if(document.getElementById('order-assistant-container')) return; var html=''
    +'<div class="chatbot-window" id="order-assistant-window">'
    +'  <div class="chatbot-header">'
    +'    <div class="chatbot-head-left">'
    +'      <img src="'+ url('/assets/images/logo.png') +'" alt="Bot" class="chatbot-avatar" width="28" height="28">'
    +'      <div class="chatbot-title-wrap">'
    +'        <div class="chatbot-title">Order Assistant</div>'
    +'        <div class="chatbot-status"><span class="presence-dot"></span><span id="order-assistant-status">Online</span></div>'
    +'      </div>'
    +'    </div>'
    +'    <div class="chatbot-head-right">'
    +'      <button class="chatbot-lang" id="order-assistant-lang" title="Language">Language ▾</button>'
      +'      <div class="chatbot-lang-menu" id="order-assistant-lang-menu" style="display:none">'
    +'        <div class="chatbot-lang-item" data-lang="en">English</div>'
    +'        <div class="chatbot-lang-item" data-lang="tl">Tagalog</div>'
    +'      </div>'
  // Close icon kept for window hide only (not conversation close)
  +'      <button class="chatbot-close" id="order-assistant-hide" aria-label="Hide">×</button>'
    +'    </div>'
    +'  </div>'
    +'  <div class="chatbot-body">'
    +'    <div class="chatbot-messages" id="order-assistant-messages"></div>'
    +'    <div class="chatbot-input-wrap">'
    +'      <input type="text" id="order-assistant-input" class="chatbot-input" placeholder="Type your message..." />'
    +'      <button id="order-assistant-send" class="chatbot-send">Send</button>'
    +'    </div>'
    +'  </div>'
    +'</div>';
    var wrap=document.createElement('div'); wrap.id='order-assistant-container'; wrap.className='chatbot-container'; wrap.innerHTML=html; document.body.appendChild(wrap);
  els.root=wrap; els.win=document.getElementById('order-assistant-window'); els.msgs=document.getElementById('order-assistant-messages'); els.input=document.getElementById('order-assistant-input'); els.send=document.getElementById('order-assistant-send'); els.status=document.getElementById('order-assistant-status'); els.langBtn=document.getElementById('order-assistant-lang'); els.langMenu=document.getElementById('order-assistant-lang-menu'); var hideBtn=document.getElementById('order-assistant-hide'); if(hideBtn){ hideBtn.onclick=function(){ els.win.style.display='none'; }; }
    els.send.onclick=onSend; els.input.addEventListener('keydown', function(e){ if(e.key==='Enter') onSend(); });
    if(els.langBtn){ els.langBtn.onclick=function(e){ e.stopPropagation(); toggleLangMenu(true); }; document.addEventListener('click', function(){ toggleLangMenu(false); }); }
    if(els.langMenu){ els.langMenu.querySelectorAll('.chatbot-lang-item').forEach(function(it){ it.addEventListener('click', function(){ setLang(it.getAttribute('data-lang'), true); toggleLangMenu(false); greet(); }); }); }
  var stored=null; try{ stored=localStorage.getItem('oa_lang'); }catch(e){}
  // Default to English unless the user has manually chosen a language before
  var initialLang=stored||'en'; setLang(initialLang);
  }
  function appendMessage(text, role){
    if(!els.msgs) return; // if UI isn't mounted yet, skip
    var msg=document.createElement('div'); msg.className='msg '+role; var avatar=document.createElement('div'); avatar.className='avatar-wrap';
    if(role==='bot' || role==='seller'){
      var img=document.createElement('img'); img.src=url('/assets/images/logo.png'); img.alt='Bot'; img.className='avatar'; img.width=28; img.height=28; avatar.appendChild(img);
    } else { var u=document.createElement('div'); u.className='avatar user'; u.textContent='You'; avatar.appendChild(u); }
    var bubble=document.createElement('div'); bubble.className='bubble';
    if(role==='seller'){
      bubble.style.cssText='background:#ffffff;border:1px solid #dbeafe;color:#1e3a8a;border-radius:16px;padding:10px 12px;position:relative;';
      var label=document.createElement('div'); label.textContent='Seller'; label.style.cssText='font-size:11px;font-weight:600;color:#1d4ed8;margin-bottom:4px;letter-spacing:.5px'; bubble.appendChild(label);
      var body=document.createElement('div'); body.textContent=text.replace(/^\s*SELLER:?\s*/i,''); bubble.appendChild(body);
    } else if(role==='bot') {
      bubble.style.cssText='background:#ffffff;border:1px solid #e5e7eb;color:#1f2937;border-radius:20px 20px 20px 4px;padding:10px 12px;';
      bubble.textContent=text;
    } else { // user
      bubble.style.cssText='background:#0475d2;color:#fff;border-radius:20px 20px 4px 20px;padding:10px 12px;';
      bubble.textContent=text;
    }
    if(role==='user'){ msg.appendChild(bubble); msg.appendChild(avatar);} else { msg.appendChild(avatar); msg.appendChild(bubble);} els.msgs.appendChild(msg); els.msgs.scrollTop=els.msgs.scrollHeight;
  }
  function clean(text){ var s=(text||'').toLowerCase().trim(); var noise=[/^how to\s+/i,/^how do i\s+/i,/^how do you\s+/i,/^how can i\s+/i,/^i want to\s+/i,/^i would like to\s+/i,/^i need to\s+/i,/^what is\s+/i,/^what's\s+/i,/^what\s+/i,/^please\s+/i,/^can you\s+/i,/^could you\s+/i,/^would you\s+/i,/^tell me\s+/i]; noise.forEach(function(rx){ s=s.replace(rx,''); }); return s; }
  var REGION_HINT_RE=/(\bNCR\b|metro\s*manila|manila|quezon\s*city|\bqc\b|makati|pasig|mandaluyong|taguig|pasay|\bR4A\b|calabarzon|cavite|laguna|batangas|rizal|quezon(?!\s*city)|\bR4B\b|mimaropa|mindoro|marinduque|romblon|palawan|\bR5\b|bicol|albay|camarines|catanduanes|masbate|sorsogon|\bLuzon\b|north\s*luzon|central\s*luzon|ilocos(?:\s*norte|\s*sur)?|la\s*union|pangasinan|cagayan(?!\s*de\s*oro)|isabela|nueva\s*vizcaya|quirino|batanes|bulacan|pampanga|tarlac|bataan|zambales|nueva\s*ecija|aurora|benguet|ifugao|mountain\s*province|abra|apayao|kalinga|\bVisayas\b|cebu|iloilo|leyte|samar|bohol|negros|\bMindanao\b|davao|zamboanga|cagayan\s*de\s*oro|butuan|general\s*santos)/i;
  function intentFor(text){ var raw=(text||''); var t=clean(raw); // specific intents first
    if(/^\s*@seller\b/.test(raw)) return 'seller';
    if(/\b(how\s*to\s*order|paano\s*(mag\s*)?order|paano\s*umorder)\b/i.test(raw)) return 'how_to_order';
    if(/(estimated\s*delivery|when\s*will\s*it\s*(arrive|get\s*delivered)|kailan\s*(darating|ma-deliver)|\beta\b|delivery\s*date)/i.test(raw)) return 'eta';
    var hasRegion=REGION_HINT_RE.test(raw);
    var hasShipWord=/(ship|shipping|delivery|deliver|courier|eta|arrive|lead\s*time|date)/i.test(raw);
    if(hasRegion && hasShipWord) return 'eta';
    if(/\border\b|buy|purchase|place\s*order|order now/.test(t)) return 'order';
    if(/\bprice\b|\bcost\b|quotation|quote/.test(t)) return 'price';
    if(/payment|pay|gcash|cod|cash on delivery|bank transfer|card/.test(t)) return 'payment';
    if(/((shipping|delivery)\s*(fee|fees|cost|costs|charge|charges|price|pricing))|how\s*much\s*(is\s*)?(shipping|delivery)|freight|cargo\s*fee|courier\s*(fee|charge|charges)|magkano.*(shipping|delivery|padala)/.test(t)) return 'shipping_fee';
  if(/\bship\b|shipping|delivery|deliver|courier|lead time|tracking|track/.test(t)) return hasRegion? 'eta' : 'shipping';
    if(/status|where.*order|when.*arrive|eta|pending|processing|shipped/.test(t)) return 'order_status';
    if(/\bstock\b|available|availability|in stock|out of stock|restock/.test(t)) return 'stock';
    if(/discount|bulk|wholesale|price break|promo|sale|offer/.test(t)) return 'discount';
    if(/warranty|guarantee|return|refund|exchange|replacement|defect|broken|damaged/.test(t)) return 'warranty';
    if(/install|installation|setup|service|maintenance|repair|technician/.test(t)) return 'installation';
    if(/\bspecs?\b|specification|size|dimension|weight|capacity|voltage|phase|mm|cm|inch|kg/.test(t)) return 'specs';
    if(/invoice|receipt|billing|payment confirmation|paid|payment received|reference/.test(t)) return 'invoice';
    if(/account|login|signin|sign in|register|signup|password/.test(t)) return 'account';
    if(/cart|checkout|check out|add to cart|remove from cart/.test(t)) return 'cart';
    if(/cancel( order)?|change my mind|wrong item/.test(t)) return 'cancel';
    if(/change( address| order| item)|edit order|modify order|update address|add item|remove item/.test(t)) return 'change_order';
    if(/\b(deliver to|ship to|coverage|areas|province|international)\b/.test(t)) return 'coverage';
    if(/language|translate|tagalog|filipino|chinese|korean|thai|vietnam|spanish|portuguese|bahasa|malay|singapore/i.test(raw)) return 'language';
    if(/pickup|pick up|collect|store pickup/.test(t)) return 'pickup';
    if(/website|site|page|not loading|error|bug|crash|cannot|can't|won't|issue/.test(t)) return 'technical';
    if(/contact|support|help|phone|email|office|address|call us|message us/.test(t)) return 'contact';
    if(/hours|open|close|business hours|working hours|location|where are you|visit us/.test(t)) return 'hours';
    if(/photo|image|picture|gallery|thumbnail|no image/.test(t)) return 'photo';
    if(/privacy|secure|security|data|personal|gdpr|policy/.test(t)) return 'privacy';
  if(/manager|owner|admin|sales|escalate|complaint/.test(t)) return 'manager';
  if(hasRegion) return 'eta';
    return 'fallback'; }
  var L = {
    greeting: { 'en': 'Hi, welcome. How can I help?', 'tl': 'Hi! Maligayang pagdating. Paano kita matutulungan?' },
    reading: { 'en': 'Reading…', 'tl': 'Binabasa…' },
    typing: { 'en': 'Typing…', 'tl': 'Nagta-type…' }
  };
  var REPLIES = {
    seller: { 'en': '✉️ Connecting you to our seller team. Your message has been sent!', 'tl': '✉️ Kinokonekta kita sa aming seller team. Naipadala na ang inyong mensahe!' },
    installation: { 'en': 'Yes, installation is free with your purchase, and our team will assist you professionally.', 'tl': 'Libre ang installation kasama ng iyong binili; tutulungan ka ng aming team.' },
    shipping_fee: { 'en': 'Shipping fee varies by location and weight. The exact amount appears at checkout. Orders with 5 or more pieces may qualify for FREE shipping.', 'tl': 'Nag-iiba ang shipping fee depende sa lokasyon at bigat. Lalabas ang eksaktong fee sa checkout. Ang 5+ piraso ay maaaring may FREE shipping.' },
    order_status: { 'en': 'Share your order number and I’ll check the status. You can also open “My Orders”.', 'tl': 'Ibigay ang order number at iche-check ko ang status. Maaari mo ring buksan ang “My Orders”.' },
    cancel: { 'en': 'To cancel, send your order number and reason. If it has shipped, we’ll help with a return instead.', 'tl': 'Para i-cancel, ibigay ang order number at dahilan. Kung naipadala na, tutulungan ka namin sa return.' },
    change_order: { 'en': 'Need to change address or items? Send your order number and the update. If not shipped yet, we’ll adjust it.', 'tl': 'Kailangang baguhin ang address o items? Ibigay ang order number at pagbabago. Kung di pa shipped, aayusin namin.' },
    coverage: { 'en': 'We ship nationwide. Fee depends on address and weight. Share your city/province for an estimate.', 'tl': 'Nagpapadala kami nationwide. Depende ang fee sa address at bigat. Ibigay ang lungsod/probinsya para sa estimate.' },
    cart: { 'en': 'Use the Cart icon to review items, then click Checkout to place the order. You can edit quantity or remove items there.', 'tl': 'I-click ang Cart para tingnan ang items, at Checkout para umorder. Maaari mong baguhin ang dami o alisin ang items.' },
    language: { 'en': 'Switch language via “Lang ▾” or just type in your language—I\'ll reply the same way.', 'tl': 'Puwede sa “Lang ▾” para magpalit ng wika, o mag-type sa iyong wika at ganoon din ako sasagot.' },
    pickup: { 'en': 'Store pickup may be arranged for selected items. Share your city and product; we’ll confirm availability.', 'tl': 'Maaaring mag-store pickup sa piling items. Ibigay ang lungsod at produkto para ma-confirm.' },
    technical: { 'en': 'If the site isn’t working, try refresh and clear cache. Tell me the page and any error text so we can fix it.', 'tl': 'Kung may aberya sa site, subukan ang refresh at clear cache. Ibigay ang page at error para maayos namin.' },
    order: { 'en': 'Open the product page and tap “Order Now”. Fill your details, choose payment, and submit.', 'tl': 'Buksan ang pahina ng produkto at i-tap ang “Order Now”. Ilagay ang detalye mo, piliin ang bayad, at isumite.' },
    price: { 'en': 'Prices are on each product page. For a quote, send the product name, quantity, and delivery location.', 'tl': 'Makikita ang presyo sa bawat pahina. Para sa quote, ibigay ang produkto, dami, at lokasyon.' },
    payment: { 'en': 'We accept COD at eligible addresses.', 'tl': 'Tumatanggap kami ng COD (kung available sa lugar mo). Sabihin lang.' },
    shipping: { 'en': 'Share your region/province (e.g., NCR, R4A, R4B, R5, Luzon, Visayas, Mindanao) and I\'ll calculate the delivery date for you.', 'tl': 'Ibahagi ang iyong rehiyon/probinsya (hal. NCR, R4A, R4B, R5, Luzon, Visayas, Mindanao) at kakalkulahin ko ang petsa ng delivery.' },
    stock: { 'en': 'Availability varies by product. Share the product and quantity to check stock and ETA.', 'tl': 'Iba-iba ang availability. Ibigay ang produkto at dami para macheck ang stock at ETA.' },
    discount: { 'en': 'Promo: Buy 4 pieces of any product and get 5% OFF. Buy 5+ pieces and get FREE shipping.', 'tl': 'Promo: Kapag 4 piraso ang binili, may 5% OFF. Kapag 5 piraso pataas, FREE shipping.' },
    warranty: {
      'en': 'Warranty becomes available after your order is received (Order Received). Go to My Orders → View Warranty & Receipt. To claim, present this receipt and the product with its serial number at our service center, or email support. You can also contact the seller in chat by typing @seller. Statuses you may see: Active (valid and unused), Claimed (in progress), Completed (service finished), Expired (coverage ended).',
      'tl': 'Available ang warranty kapag natanggap mo na ang order (Order Received). Pumunta sa My Orders → View Warranty & Receipt. Para mag-claim, ipakita ang resibo at ang produkto na may serial number sa aming service center, o mag-email sa support. Maaari ring kontakin ang seller sa chat sa pamamagitan ng @seller. Mga status na makikita: Active (valid at hindi pa nagamit), Claimed (kasalukuyang pinoproseso), Completed (tapos na ang serbisyo), Expired (natapos na ang coverage).'
    },
    specs: { 'en': 'Specs are listed on each product page. Tell me what detail you need.', 'tl': 'Makikita ang specs sa bawat pahina. Sabihin kung anong detalye ang kailangan mo.' },
    invoice: { 'en': 'Share your order number; we’ll verify and update your order.', 'tl': 'Ibigay ang order number; iva-verify namin at ia-update ang order mo.' },
    account: { 'en': 'Use Sign In or Register at the top. Note: Your password is set once at registration and cannot be changed. Need help? Type @seller to contact us.', 'tl': 'Gamitin ang Sign In o Register sa itaas. Tandaan: Isang beses lang ise-set ang password at hindi na mababago. Kailangan ng tulong? I-type ang @seller.' },
    contact: { 'en': 'Reach us via the Contact page or the phone/email listed there.', 'tl': 'Maaaring kontakin kami sa Contact page o sa phone/email na nakalagay doon.' },
    hours: { 'en': 'Business hours and address are on the below.', 'tl': 'Business hours at address ay nasa ibaba.' },
    photo: { 'en': 'Product images are on each product page. If something is missing, tell me which product.', 'tl': 'Makikita ang mga larawan sa pahina ng produkto. Kung may kulang, sabihin ang produkto.' },
    privacy: { 'en': 'We handle data per our privacy policy, only what’s required.', 'tl': 'Pinapangalagaan namin ang data ayon sa privacy policy at tanging kailangan lang para sa order.' },
    manager: { 'en': 'Share a short summary and I’ll connect you to the right person.', 'tl': 'Magbigay ng maikling detalye at iko-connect kita sa tamang tao.' },
    fallback: { 'en': 'Apologies, I can only assist with questions related to this website’s products, services, and orders. If you need help, please share the product name, quantity, and delivery location.', 'tl': 'Paumanhin, mga tanong tungkol lamang sa mga produkto, serbisyo, at orders ng website na ito ang maaari kong sagutin. Kung kailangan mo ng tulong, ilahad ang pangalan ng produkto, dami, at lokasyon.' }
  };

  function tKey(key){ var lang=state.manualLang||state.lang||'en'; return (L[key]&&(L[key][lang]||L[key]['en']))||''; }
  // Offensive filter for @seller messages
  function normalizeOffense(s){ s=(s||'').toLowerCase(); var map={'0':'o','1':'i','3':'e','4':'a','5':'s','7':'t','@':'a','$':'s','!':'i','*':'','+':'t'}; s=s.replace(/[0-9@$!*+]/g,function(c){return map[c]!==undefined?map[c]:'';}); s=s.replace(/[^a-zñáéíóúü ]+/g,''); s=s.replace(/\s+/g,' '); s=s.replace(/([a-zñ])\1{2,}/g,'$1$1'); return s; }
  function isOffensive(msg){ var n=normalizeOffense(msg); var bad=['puta','putangina','tangina','gago','bobo','ulol','pakyu','inutil','bwisit','tarantado','punyeta','puta ina','putang ina','fuck','fucking','motherfucker','mf','shit','bitch','bastard','asshole','dick','pussy','slut','whore','cunt']; for(var i=0;i<bad.length;i++){ if(n.indexOf(bad[i])>-1) return true; } return false; }
  // Region-based ETA
  var REGION_RULES=[
    {key:'NCR', days:[2,3], match:/(\bNCR\b|metro\s*manila|manila|quezon\s*city|\bqc\b|makati|pasig|mandaluyong|taguig|pasay)/i},
    {key:'R4A', days:[2,3], match:/(\bR4A\b|calabarzon|cavite|laguna|batangas|rizal|quezon(?! city))/i},
    {key:'R4B', days:[3,4], match:/(\bR4B\b|mimaropa|mindoro|marinduque|romblon|palawan)/i},
    {key:'R5', days:[5,7], match:/(\bR5\b|bicol|albay|camarines|catanduanes|masbate|sorsogon)/i},
  {key:'Luzon', days:[5,7], match:/(\bluzon\b|north\s*luzon|central\s*luzon|ilocos(?:\s*norte|\s*sur)?|la\s*union|pangasinan|cagayan(?!\s*de\s*oro)|isabela|nueva\s*vizcaya|quirino|batanes|bulacan|pampanga|tarlac|bataan|zambales|nueva\s*ecija|aurora|benguet|ifugao|mountain\s*province|abra|apayao|kalinga)/i},
    {key:'Visayas', days:[15,20], match:/(\bvisayas\b|cebu|iloilo|leyte|samar|bohol|negros)/i},
    {key:'Mindanao', days:[15,20], match:/(\bmindanao\b|davao|zamboanga|cagayan de oro|butuan|general santos)/i}
  ];
  function detectRegion(text){ var src=(text||'')+' '+(window.currentUserRegion||'')+' '+(window.currentUserProvince||''); for(var i=0;i<REGION_RULES.length;i++){ if(REGION_RULES[i].match.test(src)) return REGION_RULES[i]; } return null; }
  function addDays(d,n){ var c=new Date(d.getFullYear(),d.getMonth(),d.getDate()); c.setDate(c.getDate()+n); return c; }
  function fmtDate(d){ var m=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; return m[d.getMonth()]+' '+d.getDate(); }
  function etaReply(text, lang){ var r=detectRegion(text); if(!r){ var ask={en:'Please share your region or province (e.g., NCR, R4A, R4B, R5, Luzon, Visayas, Mindanao) so I can calculate the delivery date.', tl:'Paki-bigay ang iyong rehiyon o probinsya (hal. NCR, R4A, R4B, R5, Luzon, Visayas, Mindanao) para makalkula ko ang delivery date.'}; return ask[lang]||ask.en; } var min=r.days[0], max=r.days[1]; var now=new Date(); var d1=addDays(now,min), d2=addDays(now,max); var range=(lang==='tl'? (min+'–'+max+' araw') : (min+'–'+max+' days')); var when=(lang==='tl'? 'darating sa pagitan ng '+fmtDate(d1)+' at '+fmtDate(d2) : 'arrives between '+fmtDate(d1)+' and '+fmtDate(d2)); var label=(lang==='tl'? 'Tinatayang delivery para sa ' : 'Estimated delivery for '); return label + r.key + ': ' + range + ' ('+when+').'; }
  function parseHowToOrderProduct(text){ var m=(text||'').match(/how\s*to\s*order\s+(.+)|paano\s*(?:mag\s*)?order\s+(.+)|paano\s*umorder\s+(.+)/i); var name=''; if(m){ name=(m[1]||m[2]||m[3]||'').trim(); name=name.replace(/[.?!]+$/,''); }
    if(!name && window.ORDER_ASSISTANT_PRODUCT) name=window.ORDER_ASSISTANT_PRODUCT; return name; }
  function howToOrderReply(text, lang){ var product=parseHowToOrderProduct(text); if(lang==='tl'){ return 'Ganito mag-order'+(product? ' ng '+product:'')+': 1) Buksan ang pahina ng produkto. 2) I-click ang “Order Now”. 3) Ilagay ang shipping details. 4) Piliin ang paraan ng bayad (karaniwang COD kung available). 5) I-review at i-Place Order. Makikita mo ang order sa “My Orders”.'; }
    return 'Here’s how to order'+(product? ' '+product:'')+': 1) Open the product page. 2) Click “Order Now”. 3) Enter your shipping details. 4) Choose a payment method (typically COD if available). 5) Review and Place Order. You can track it under “My Orders”.'; }
  function replyFor(text){ var intent=intentFor(text); var lang=state.manualLang||state.lang||'en'; if(intent==='how_to_order') return howToOrderReply(text, lang); if(intent==='eta') return etaReply(text, lang); var bucket=REPLIES[intent]||{}; return bucket[lang]||bucket['en']||''; }
  function setStatus(textKey){ if(!els.status) return; var txt=textKey? tKey(textKey):'Online'; els.status.textContent=txt||'Online'; }
  function showIndicator(kind){ var msg=document.createElement('div'); msg.className='msg bot indicator '+kind; var avatar=document.createElement('div'); avatar.className='avatar-wrap'; var img=document.createElement('img'); img.src=url('/assets/images/logo.png'); img.alt='Bot'; img.className='avatar'; avatar.appendChild(img); var bubble=document.createElement('div'); bubble.className='bubble'; if(kind==='typing'){ bubble.innerHTML='<span class="typing-dots"><span></span><span></span><span></span>'; } else { bubble.textContent=tKey('reading'); } msg.appendChild(avatar); msg.appendChild(bubble); msg.dataset.kind=kind; els.msgs.appendChild(msg); els.msgs.scrollTop=els.msgs.scrollHeight; return msg; }
  function removeIndicators(){ var elsList=els.msgs? els.msgs.querySelectorAll('.msg.indicator'):[]; elsList.forEach(function(n){ if(n&&n.parentNode) n.parentNode.removeChild(n); }); }
  function detectLangFromText(text){ if(!text) return state.lang||'en'; // simple detection: look for Tagalog/Pilipino cues
    if(/\b(magkano|paano|po|kayo|salamat|kumusta|pagbili|padala|bayad|oo|hindi)\b/i.test(text)) return 'tl';
    return 'en'; }
  function setLang(lang, manual){ if(!lang) lang='en'; lang=String(lang).toLowerCase(); // normalize only en/tl
    if(lang==='tl' || lang==='tagalog' || lang==='fil' || lang==='filipino') lang='tl'; else lang='en'; var finalLang = (lang==='tl')? 'tl' : 'en'; state.lang=finalLang; if(manual){ state.manualLang=finalLang; try{ localStorage.setItem('oa_lang', finalLang);}catch(e){} } if(els.root){ els.root.classList.remove('lang-en','lang-tl'); els.root.classList.add('lang-'+finalLang);} if(els.input){ var ph={'en':'Type your message…','tl':'Mag-type ng mensahe…'}; els.input.placeholder=ph[finalLang]||ph['en']; } }
  function clearBotOnly(){ if(!els.msgs) return; var nodes=[].slice.call(els.msgs.querySelectorAll('.msg.bot')); nodes.forEach(function(n){ // keep admin "seller" style messages (not class bot)
      if(n && n.parentNode){ n.parentNode.removeChild(n); }
  }); }
  function greet(){ appendMessage(tKey('greeting'), 'bot'); checkForAdminReplies(); startMessagePolling(); }
  var messageCheckInterval; var lastCheckedMessageId = 0; var hasNewAdminMessage = false; var newAdminCount = 0;
  function updateAccountNotification(){ try{ var chatBtn = document.querySelector('.chat-icon'); if(!chatBtn) return; chatBtn.style.position='relative'; var badge = chatBtn.querySelector('.msg-notification'); if(newAdminCount>0){ if(!badge){ badge=document.createElement('span'); badge.className='msg-notification'; chatBtn.appendChild(badge); }
      badge.textContent = String(newAdminCount);
      badge.style.cssText = 'position:absolute;top:-6px;right:-10px;background:#ef4444;color:#fff;border-radius:999px;min-width:18px;height:18px;padding:0 4px;font-size:12px;line-height:18px;display:flex;align-items:center;justify-content:center;font-weight:700;box-shadow:0 0 0 2px #fff;';
    } else if(badge){ badge.remove(); }
  }catch(e){} }
  function removeAccountNotification(){ try{ var chatBtn=document.querySelector('.chat-icon'); if(chatBtn){ var n=chatBtn.querySelector('.msg-notification'); if(n) n.remove(); } }catch(e){} }
  function checkForAdminReplies(){ try{ var reqUrl=url('/chatbot_api.php?action=get_messages'); try{ if(window.currentUserName){ reqUrl += ('&customer_name='+encodeURIComponent(window.currentUserName)); } }catch(_e){} fetch(reqUrl,{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(data){ if(!data||!data.success) return; if(data.closed){ // conversation closed: show notice once then stop polling
        if(els.msgs && !els.msgs.querySelector('.msg.closed')){ appendMessage('This conversation was closed by the seller. Start a new message anytime.','bot'); var notice=els.msgs.lastChild; if(notice) notice.classList.add('closed'); }
        if(messageCheckInterval){ clearInterval(messageCheckInterval); messageCheckInterval=null; }
        return;
      }
  var msgs = data.messages||[];
  // Append only newly arrived admin messages to window (if open)
  msgs.forEach(function(msg){ if(msg.from_admin==1 && msg.id > lastCheckedMessageId){ if(els.msgs){ appendMessage(msg.message,'seller'); } lastCheckedMessageId = Math.max(lastCheckedMessageId, msg.id); hasNewAdminMessage = true; } });
  // Compute unseen count for header badge
  var unseen = 0; for(var i=0;i<msgs.length;i++){ if(msgs[i].from_admin==1 && String(msgs[i].seen_by_customer)!='1'){ unseen++; } }
  newAdminCount = unseen; updateAccountNotification();
    }).catch(function(){}); }catch(e){} }
  function startMessagePolling(){ if(messageCheckInterval) clearInterval(messageCheckInterval); messageCheckInterval = setInterval(checkForAdminReplies, 3000); }
  function sendSellerMessage(message){ try{ var formData=new FormData(); formData.append('action','seller'); formData.append('message',message); formData.append('customer_name',window.currentUserName||'Anonymous'); fetch(url('/chatbot_api.php'),{method:'POST',body:formData,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(data){ removeIndicators(); setStatus(); if(data&&data.success){ var lang=state.manualLang||state.lang||'en'; var reply=(REPLIES.seller&&REPLIES.seller[lang])||REPLIES.seller['en']||'Message sent to seller!'; appendMessage(reply,'bot'); }else{ appendMessage('Sorry, failed to send message. Please try again.','bot'); } }).catch(function(){ removeIndicators(); setStatus(); appendMessage('Sorry, failed to send message. Please try again.','bot'); }); }catch(e){ removeIndicators(); setStatus(); appendMessage('Sorry, failed to send message. Please try again.','bot'); } }
  function onSend(){ var text=(els.input.value||'').trim(); if(!text) return; appendMessage(text, 'user'); els.input.value=''; var intent=intentFor(text); /* Keep English unless the user manually selects a language */ if(intent==='seller'){ setStatus('reading'); showIndicator('reading'); var messagePart=text.replace(/^@seller\s*/i,'').trim(); if(isOffensive(messagePart)){ removeIndicators(); setStatus(); var lang=state.manualLang||state.lang||'en'; var msg={en:'Apologies, your message was not sent because it contains offensive or inappropriate language. Please rephrase and avoid offensive words.', tl:'Paumanhin, hindi naipadala ang iyong mensahe dahil naglalaman ito ng hindi angkop o bastos na salita. Paki-ayos ang mensahe at iwasan ang malaswang salita.'}; appendMessage(msg[lang]||msg.en, 'bot'); return; } sendSellerMessage(messagePart||text); return; } setStatus('reading'); showIndicator('reading'); setTimeout(function(){ removeIndicators(); setStatus('typing'); showIndicator('typing'); setTimeout(function(){ removeIndicators(); setStatus(); var answer=replyFor(text); appendMessage(answer||tKey('greeting'), 'bot'); if(intent==='order_status'){ try{ fetchPendingOrdersCount(); }catch(e){} } },1100); },650); }
  function mount(){ ensureUI(); }
  window.OrderAssistant={ init:function(opts){ mount(); if(opts&&opts.productName){ window.ORDER_ASSISTANT_PRODUCT=opts.productName; } try{ window.OrderAssistant.open(); }catch(e){} }, open:function(opts){ mount(); if(opts&&opts.productName){ window.ORDER_ASSISTANT_PRODUCT=opts.productName; } els.win.style.display='block'; // when opened by customer, clear notification and bot-only messages
    removeAccountNotification(); newAdminCount=0; updateAccountNotification();
    // Mark messages as seen
    try{ var seenUrl=url('/chatbot_api.php?action=get_messages&mark_seen=1'); if(window.currentUserName){ seenUrl += ('&customer_name='+encodeURIComponent(window.currentUserName)); } fetch(seenUrl,{credentials:'same-origin'}).catch(function(){}); }catch(_e){}
    greet(); // start polling
    clearBotOnly(); // then prune bot-only replies so seller/user history remains
    els.input.focus(); } };
  function toggleLangMenu(show){ if(!els.langMenu) return; els.langMenu.style.display= show? 'block':'none'; }
  try{
    if(!window.__OA_boundToggle){
      window.__OA_boundToggle=true;
      document.addEventListener('click', function(e){
        var t=e.target; if(!t) return;
        var isChatIcon=(t.classList&&t.classList.contains('chat-icon'))||(t.closest&&t.closest('.chat-icon'));
        if(isChatIcon){
          try{ window.OrderAssistant.open(); }
          catch(err){ try{ mount(); window.OrderAssistant.open(); }catch(_e){} }
        }
      });
    }
  }catch(_err){}
  // Start lightweight polling for notifications even when chat is closed
  try{ startMessagePolling(); }catch(_e){}
  function fetchPendingOrdersCount(){ try{
    fetch(url('/orders.php?count=pending'), { credentials: 'same-origin' })
      .then(function(r){ return r.text(); })
      .then(function(txt){ var json=null; try{ json=JSON.parse(txt);}catch(e){} var n=null; if(json&&typeof json==='object'){ var keys=['pending','count','pending_count','orders','value']; for(var i=0;i<keys.length;i++){ var k=keys[i]; if(typeof json[k]==='number'){ n=json[k]; break; } } if(n===null){ Object.keys(json).some(function(k){ if(typeof json[k]==='number'){ n=json[k]; return true;} return false; }); } } if(n===null) return; var lang=state.manualLang||state.lang||'en'; var M={'en':'Pending orders (site): '+n,'tl':'Nakabinbing order (site): '+n}; appendMessage(M[lang]||M['en'], 'bot'); }).catch(function(_e){ }); }catch(_err){} }
  function sendSellerMessage(message){ try{ var formData=new FormData(); formData.append('action','seller'); formData.append('message',message); formData.append('customer_name',window.currentUserName||'Anonymous'); fetch(url('/chatbot_api.php'),{method:'POST',body:formData,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(data){ removeIndicators(); setStatus(); if(data&&data.success){ var lang=state.manualLang||state.lang||'en'; var reply=(REPLIES.seller&&REPLIES.seller[lang])||REPLIES.seller['en']||'Message sent to seller!'; appendMessage(reply,'bot'); }else{ appendMessage('Sorry, failed to send message. Please try again.','bot'); } }).catch(function(){ removeIndicators(); setStatus(); appendMessage('Sorry, failed to send message. Please try again.','bot'); }); }catch(e){ removeIndicators(); setStatus(); appendMessage('Sorry, failed to send message. Please try again.','bot'); } }
})();

