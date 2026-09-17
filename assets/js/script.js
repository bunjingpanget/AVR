(function(){
  var BASE=(window.BASE_URL||'').replace(/\/+$/,'');
  var badgeEl=null; function badge(){ return badgeEl || (badgeEl=document.querySelector('.cart-badge')); }
  function post(url, data){ return fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)}).then(r=>r.json()); }
  function flash(msg){
    var n=document.createElement('div');
    n.textContent=msg; n.style.cssText='position:fixed;top:14px;right:14px;background:#0f172a;color:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.25);font-size:14px;z-index:2000';
    document.body.appendChild(n); setTimeout(()=>{n.style.opacity='0'; n.style.transition='opacity .4s'; setTimeout(()=>n.remove(),400);},2200);
  }
  function updCount(c){ if(badge()){ badge().textContent=c; badge().style.display=c>0?'inline-flex':'none'; } }
  function updPreview(html){
    var nav=document.querySelector('.cart-nav'); if(!nav) return;
    var pv=nav.querySelector('.cart-preview'); if(!pv) return;
    pv.innerHTML=html;
  }
  window.addToCart = function(pid, qty){
    qty = qty && qty>0 ? qty : 1;
    post(BASE+'/cart.php',{action:'add',id:pid,qty:qty}).then(function(res){
      if(res.login){ location.href=BASE+'/signin.php'; return; }
      if(res.ok){
        updCount(res.count||0);
        if(res.preview){ updPreview(res.preview); }
        var added = (typeof res.addedQty !== 'undefined' && res.addedQty !== null) ? parseInt(res.addedQty) : null;
        if(added === 0){
          flash('Cannot add more: reached available stock');
        } else if(added > 0){
          flash('Added '+ added +' item'+(added>1?'s':'')+' to cart');
        } else {
          // Fallback if server did not send addedQty
          flash('Added to cart');
        }
      }
    });
  };
  window.updateCart = function(pid, qty){
    post(BASE+'/cart.php',{action:'update',id:pid,qty:qty}).then(function(res){
      if(res.login){ location.href=BASE+'/signin.php'; return; }
      if(res.ok){ if(res.count!==undefined) updCount(res.count); location.reload(); }
    });
  };
  window.orderNow = function(pid){ location.href=BASE + '/product.php?id=' + encodeURIComponent(pid); };
})();

// Mobile header auto-hide on scroll (customer site)
(function(){
  var header=document.querySelector('.site-header'); if(!header) return;
  var nav=document.querySelector('.site-header .nav');
  var last=window.scrollY||0, ticking=false; var TH=12; var DESKTOP=981;
  function update(){
    ticking=false;
    if(window.innerWidth>=DESKTOP){ header.classList.remove('hide'); last=window.scrollY||0; return; }
    var y=window.scrollY||0, d=y-last, atTop=y<=0;
    if(atTop){ header.classList.remove('hide'); }
    else if(d>TH){ if(!(nav&&nav.classList.contains('open'))) header.classList.add('hide'); }
    else if(d<-TH){ header.classList.remove('hide'); }
    last=y;
  }
  window.addEventListener('scroll', function(){ if(!ticking){ requestAnimationFrame(update); ticking=true; } }, {passive:true});
  window.addEventListener('resize', update, {passive:true});
  update();
})();

// AddressPicker: Lightweight PH address picker using PSGC API. Exposes window.AddressPicker.mount(...)
(function(){
  var PSGC = {
    prov: 'https://psgc.gitlab.io/api/provinces/',
    ncrCities: 'https://psgc.gitlab.io/api/regions/130000000/cities-municipalities/',
    provMunicipalities: function(code){ return 'https://psgc.gitlab.io/api/provinces/'+code+'/municipalities/'; },
    provCities: function(code){ return 'https://psgc.gitlab.io/api/provinces/'+code+'/cities/'; },
    cityBarangays: function(code){ return 'https://psgc.gitlab.io/api/cities-municipalities/'+code+'/barangays/'; }
  };
  var cache = {};
  function fetchJSON(url){ if(cache[url]) return Promise.resolve(cache[url]); return fetch(url, {mode:'cors'}).then(function(r){ return r.json(); }).then(function(j){ cache[url]=j; return j; }).catch(function(){ return []; }); }
  function byNameAsc(a,b){ return (a.name||'').localeCompare(b.name||''); }
  function option(el, label, value){ var o=document.createElement('option'); o.textContent=label; o.value=value; el.appendChild(o); }

  function mount(cfg){
    var selProvince = typeof cfg.province==='string'? document.querySelector(cfg.province): cfg.province;
    var selCity     = typeof cfg.city==='string'? document.querySelector(cfg.city): cfg.city;
    var selBrgy     = typeof cfg.barangay==='string'? document.querySelector(cfg.barangay): cfg.barangay;
    var street      = typeof cfg.street==='string'? document.querySelector(cfg.street): cfg.street;
    var house       = typeof cfg.house==='string'? document.querySelector(cfg.house): cfg.house;
    var hiddenLoc   = typeof cfg.hiddenLocation==='string'? document.querySelector(cfg.hiddenLocation): cfg.hiddenLocation;
    if(!selProvince||!selCity||!selBrgy) return;

    function clear(el, ph){ while(el.firstChild) el.removeChild(el.firstChild); option(el, ph, ''); }
    function compose(){
      var parts=[house&&house.value, street&&street.value, selBrgy.value && selBrgy.options[selBrgy.selectedIndex].text, selCity.value && selCity.options[selCity.selectedIndex].text, selProvince.value && selProvince.options[selProvince.selectedIndex].text];
      var s=parts.filter(Boolean).join(', '); if(hiddenLoc) hiddenLoc.value=s; return s;
    }

    function loadProvinces(){
      clear(selProvince, 'Select Province'); option(selProvince, 'Metro Manila (NCR)', 'NCR');
      fetchJSON(PSGC.prov).then(function(list){ list.sort(byNameAsc).forEach(function(p){ option(selProvince, p.name, p.code); }); });
    }
    function loadCitiesForProvince(){
      clear(selCity, 'Select City/Municipality'); clear(selBrgy, 'Select Barangay');
      var pv = selProvince.value; if(!pv) return;
      if(pv==='NCR'){
        fetchJSON(PSGC.ncrCities).then(function(list){ list.sort(byNameAsc).forEach(function(c){ option(selCity, c.name, c.code); }); });
        return;
      }
      Promise.all([fetchJSON(PSGC.provCities(pv)), fetchJSON(PSGC.provMunicipalities(pv))]).then(function(res){
        var all = (res[0]||[]).concat(res[1]||[]); all.sort(byNameAsc).forEach(function(c){ option(selCity, c.name, c.code); });
      });
    }
    function loadBarangays(){
      clear(selBrgy, 'Select Barangay'); var c = selCity.value; if(!c) return;
      fetchJSON(PSGC.cityBarangays(c)).then(function(list){ list.sort(byNameAsc).forEach(function(b){ option(selBrgy, b.name, b.code); }); });
    }

    selProvince.addEventListener('change', function(){ loadCitiesForProvince(); compose(); });
    selCity.addEventListener('change', function(){ loadBarangays(); compose(); });
    selBrgy.addEventListener('change', compose);
    if(street) street.addEventListener('input', compose);
    if(house) house.addEventListener('input', compose);

    // Initialize
    loadProvinces();
    compose();
  }

  window.AddressPicker = { mount: mount };
})();
