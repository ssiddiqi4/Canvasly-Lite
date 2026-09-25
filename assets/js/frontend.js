/* Canvasly Lite frontend behaviours — carousel, tabs, collapse panels, alerts, counters, progress bars, video overlays, lightbox, background video and scroll interactions. */
(function(){
 'use strict';
 var I18N=(window.CanvaslyLiteFrontend&&window.CanvaslyLiteFrontend.i18n)||{};function t(key){var s=Object.prototype.hasOwnProperty.call(I18N,key)?String(I18N[key]):String(key);if(arguments.length>1){for(var i=1;i<arguments.length;i++)s=s.replace('%s',String(arguments[i]));}return s;}
 var reduceMotion=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;

 /* ---- Image carousel ---- */
 function carousel(c){
  if(c.dataset.lbReady)return;c.dataset.lbReady='1';
  var track=c.querySelector('.lb-carousel-track'),slides=track?[].slice.call(track.querySelectorAll('.lb-carousel-slide')):[];
  if(!slides.length)return;
  var show=Math.max(1,parseInt(c.dataset.show||1,10)),step=Math.max(1,parseInt(c.dataset.scroll||1,10)),fade=c.dataset.effect==='fade',loop=c.dataset.loop==='1',rtl=c.dataset.direction==='rtl';
  var pages=Math.max(1,Math.ceil((slides.length-show)/step)+1),i=0,timer=null,dots=[];
  function gap(){return parseFloat(getComputedStyle(c).getPropertyValue('--lb-carousel-spacing'))||10}
  function apply(){
   if(fade){slides.forEach(function(s,k){s.classList.toggle('is-active',k===i);s.setAttribute('aria-hidden',k===i?'false':'true')})}
   else{var w=slides[0].getBoundingClientRect().width+gap(),offset=Math.min(i*step,Math.max(0,slides.length-show))*w;track.style.transform='translateX('+(rtl?offset:-offset)+'px)';slides.forEach(function(s,k){var vis=k>=Math.min(i*step,slides.length-show)&&k<Math.min(i*step,slides.length-show)+show;s.setAttribute('aria-hidden',vis?'false':'true')})}
   dots.forEach(function(d,k){d.classList.toggle('is-active',k===i);d.setAttribute('aria-current',k===i?'true':'false')});
   if(prev){prev.disabled=!loop&&i===0}if(next){next.disabled=!loop&&i>=pages-1}
  }
  function go(n){if(loop)n=(n+pages)%pages;i=Math.max(0,Math.min(pages-1,n));apply()}
  var nextFn=function(){go(i+1)},prevFn=function(){go(i-1)},prev=null,next=null;
  if(c.dataset.arrows==='1'){prev=document.createElement('button');prev.className='lb-carousel-prev';prev.type='button';prev.setAttribute('aria-label',t('Previous slide'));prev.textContent=rtl?'›':'‹';next=document.createElement('button');next.className='lb-carousel-next';next.type='button';next.setAttribute('aria-label',t('Next slide'));next.textContent=rtl?'‹':'›';prev.addEventListener('click',function(){(rtl?nextFn:prevFn)();pauseIfNeeded()});next.addEventListener('click',function(){(rtl?prevFn:nextFn)();pauseIfNeeded()});c.append(prev,next)}
  if(c.dataset.dots==='1'&&pages>1){var d=document.createElement('div');d.className='lb-carousel-dots';d.setAttribute('role','tablist');for(var k=0;k<pages;k++){(function(n){var x=document.createElement('button');x.type='button';x.setAttribute('aria-label',t('Go to slide %s', n+1));x.addEventListener('click',function(){go(n);pauseIfNeeded()});dots.push(x);d.appendChild(x)})(k)}c.appendChild(d)}
  function start(){if(c.dataset.autoplay!=='1'||reduceMotion||pages<2)return;stop();timer=setInterval(function(){if(document.hidden)return;if(!loop&&i>=pages-1){stop();return}nextFn()},Math.max(500,parseInt(c.dataset.interval||5000,10)))}
  function stop(){if(timer){clearInterval(timer);timer=null}}
  function pauseIfNeeded(){if(c.dataset.pauseInteraction==='1')stop()}
  if(c.dataset.pauseHover==='1'){c.addEventListener('mouseenter',stop);c.addEventListener('mouseleave',start)}
  c.tabIndex=c.tabIndex>=0?c.tabIndex:0;
  c.addEventListener('keydown',function(e){if(e.key==='ArrowRight'){e.preventDefault();(rtl?prevFn:nextFn)();pauseIfNeeded()}if(e.key==='ArrowLeft'){e.preventDefault();(rtl?nextFn:prevFn)();pauseIfNeeded()}});
  var sx=null;c.addEventListener('touchstart',function(e){sx=e.touches[0].clientX},{passive:true});c.addEventListener('touchend',function(e){if(sx===null)return;var dx=e.changedTouches[0].clientX-sx;sx=null;if(Math.abs(dx)<40)return;((dx<0)!==rtl?nextFn:prevFn)();pauseIfNeeded()});
  window.addEventListener('resize',apply);
  apply();start();
 }

 /* ---- Tabs ---- */
 function tabs(w){
  if(w.dataset.lbReady)return;w.dataset.lbReady='1';
  var nav=w.querySelector(':scope > .lb-tabs-nav')||w.querySelector('.lb-tabs-nav');
  var wrap=w.querySelector(':scope > .lb-tabs-panels')||w.querySelector('.lb-tabs-panels');
  var buttons=[].slice.call((nav||w).querySelectorAll(':scope > .lb-tab-button'));
  var panels=wrap?[].slice.call(wrap.querySelectorAll(':scope > .lb-tab-panel')):[].slice.call(w.querySelectorAll(':scope > .lb-tab-panel'));
  if(!buttons.length)return;
  function activate(i,focus){buttons.forEach(function(b,n){var on=n===i;b.classList.toggle('is-active',on);b.setAttribute('aria-selected',on?'true':'false');b.tabIndex=on?0:-1;if(on&&focus)b.focus()});panels.forEach(function(p,n){p.hidden=n!==i})}
  buttons.forEach(function(b,n){b.addEventListener('click',function(){activate(n)});b.addEventListener('keydown',function(e){var vertical=w.classList.contains('lb-tabs-vertical'),fwd=vertical?'ArrowDown':'ArrowRight',back=vertical?'ArrowUp':'ArrowLeft';if(e.key===fwd){e.preventDefault();activate((n+1)%buttons.length,true)}else if(e.key===back){e.preventDefault();activate((n-1+buttons.length)%buttons.length,true)}else if(e.key==='Home'){e.preventDefault();activate(0,true)}else if(e.key==='End'){e.preventDefault();activate(buttons.length-1,true)}else if(e.key==='Enter'||e.key===' '){e.preventDefault();activate(n)}})});
  activate(Math.max(0,Math.min(buttons.length-1,parseInt(w.dataset.active||0,10))));
 }

 /* ---- Accordion / Toggle ---- */
 function collapse(root){
  if(root.dataset.lbReady)return;root.dataset.lbReady='1';
  var single=root.dataset.lbCollapse==='single',items=[].slice.call(root.querySelectorAll(':scope > .lb-collapse-item'));
  function setOpen(item,open){item.classList.toggle('is-open',open);var t=item.querySelector(':scope > .lb-collapse-title'),c=item.querySelector(':scope > .lb-collapse-content');if(t)t.setAttribute('aria-expanded',open?'true':'false');if(c)c.hidden=!open}
  function titleOf(item){return item?item.querySelector(':scope > .lb-collapse-title'):null}
  items.forEach(function(item,n){var t=titleOf(item);if(!t)return;var toggle=function(){var open=!item.classList.contains('is-open');if(single&&open)items.forEach(function(o){if(o!==item)setOpen(o,false)});setOpen(item,open)};t.addEventListener('click',toggle);t.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle()}else if(e.key==='ArrowDown'||e.key==='ArrowRight'){e.preventDefault();var next=titleOf(items[(n+1)%items.length]);if(next)next.focus()}else if(e.key==='ArrowUp'||e.key==='ArrowLeft'){e.preventDefault();var prev=titleOf(items[(n-1+items.length)%items.length]);if(prev)prev.focus()}else if(e.key==='Home'){e.preventDefault();var first=titleOf(items[0]);if(first)first.focus()}else if(e.key==='End'){e.preventDefault();var last=titleOf(items[items.length-1]);if(last)last.focus()}})});
 }

 /* ---- Alert dismiss ---- */
 function alerts(){document.addEventListener('click',function(e){var b=e.target.closest('[data-lb-dismiss]');if(!b)return;var a=b.closest('.lb-alert');if(a){a.classList.add('is-dismissed');a.setAttribute('aria-hidden','true')}})}

 /* ---- Counter ---- */
 function formatNumber(n,sep){var neg=n<0;n=Math.abs(n);var parts=String(Math.round(n*100)/100).split('.');if(sep)parts[0]=parts[0].replace(/\B(?=(\d{3})+(?!\d))/g,sep);return (neg?'-':'')+parts.join('.')}
 function counter(el){
  if(el.dataset.lbReady)return;el.dataset.lbReady='1';
  var start=parseFloat(el.dataset.start||0),end=parseFloat(el.dataset.end||0),dur=Math.max(0,parseInt(el.dataset.duration||2000,10)),sep=el.dataset.separator||'';
  if(reduceMotion||dur===0){el.textContent=formatNumber(end,sep);return}
  el.textContent=formatNumber(start,sep);var t0=null;
  function frame(ts){if(t0===null)t0=ts;var p=Math.min(1,(ts-t0)/dur),eased=1-Math.pow(1-p,3);el.textContent=formatNumber(start+(end-start)*eased,sep);if(p<1)requestAnimationFrame(frame)}
  requestAnimationFrame(frame);
 }

 /* ---- Progress bar ---- */
 function progress(fill){if(fill.dataset.lbReady)return;fill.dataset.lbReady='1';var v=parseFloat(fill.dataset.lbProgress||0);if(reduceMotion){fill.style.width=v+'%';return}fill.style.width='0%';requestAnimationFrame(function(){requestAnimationFrame(function(){fill.style.width=v+'%'})})}

 /* ---- Lightbox (images + video) ---- */
 var KIT=(window.CanvaslyLiteFrontend&&window.CanvaslyLiteFrontend.lightbox)||{};
 function kitCaption(a){
  var src=KIT.caption_source||'caption';
  if(src==='none')return '';
  if(src==='alt')return (a.getAttribute('data-lb-alt')||((a.querySelector('img')||{}).alt)||'');
  if(src==='title')return a.getAttribute('data-lb-title')||a.getAttribute('title')||'';
  return a.getAttribute('data-lb-caption')||'';
 }
 function kitGroup(a){
  var host=a.closest('.lb-gallery, .lb-carousel, .lb-page, .lb-frame-root')||document;
  return [].slice.call(host.querySelectorAll('[data-lb-lightbox="1"]')).filter(function(x){return !!x.href});
 }
 function openLightbox(content,extraClass,meta){
  meta=meta||{};
  var back=document.createElement('div');back.className='lb-lightbox'+(extraClass?' '+extraClass:'');back.setAttribute('role','dialog');back.setAttribute('aria-modal','true');
  if(KIT.overlay_color)back.style.setProperty('--lb-lightbox-overlay',KIT.overlay_color);
  if(KIT.ui_color)back.style.setProperty('--lb-lightbox-ui',KIT.ui_color);
  var close=document.createElement('button');close.type='button';close.className='lb-lightbox-close';close.setAttribute('aria-label',t('Close'));close.textContent='×';
  if(KIT.show_close===false)close.hidden=true;
  back.appendChild(close);back.appendChild(content);
  if(meta.caption){var cap=document.createElement('p');cap.className='lb-lightbox-caption';cap.textContent=meta.caption;back.appendChild(cap)}
  if(KIT.show_counter&&meta.total>1){var ct=document.createElement('div');ct.className='lb-lightbox-counter';ct.textContent=meta.index+' / '+meta.total;back.appendChild(ct)}
  if(KIT.show_fullscreen){
   var fs=document.createElement('button');fs.type='button';fs.className='lb-lightbox-fs';fs.setAttribute('aria-label',t('Fullscreen'));fs.textContent='\u26F6';
   fs.addEventListener('click',function(e){e.stopPropagation();try{if(!document.fullscreenElement)back.requestFullscreen&&back.requestFullscreen();else document.exitFullscreen&&document.exitFullscreen()}catch(err){}});
   back.appendChild(fs);
  }
  var dispose=function(){if(document.fullscreenElement){try{document.exitFullscreen()}catch(e){}}back.remove();document.removeEventListener('keydown',esc)};var esc=function(ev){if(ev.key==='Escape')dispose()};
  back.addEventListener('click',function(e){if(e.target===back||e.target===close)dispose()});
  document.body.appendChild(back);(close.hidden?back:close).focus();document.addEventListener('keydown',esc);
  return back;
 }
 function imageLightbox(a){
  var im=document.createElement('img');im.src=a.href;im.alt=a.getAttribute('data-lb-alt')||(a.querySelector('img')||{}).alt||'';
  var group=kitGroup(a),idx=Math.max(0,group.indexOf(a));
  openLightbox(im,'',{caption:kitCaption(a),index:idx+1,total:group.length});
 }

 /* ---- Video overlay / lightbox ---- */
 function buildPlayer(frame){
  var src=frame.dataset.lbVideoSrc,kind=frame.dataset.lbVideoKind;
  if(kind==='hosted'){var v=document.createElement('video');v.className='lb-video-media';v.src=src;v.autoplay=true;v.playsInline=true;(frame.dataset.lbVideoAttrs||'').split(/\s+/).forEach(function(a){if(a)v.setAttribute(a,'')});return v}
  var f=document.createElement('iframe');f.className='lb-video-media';f.src=src;f.title=t('Video player');f.allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';f.setAttribute('allowfullscreen','');return f;
 }
 function video(frame){
  if(frame.dataset.lbReady)return;frame.dataset.lbReady='1';
  var play=function(e){
   if(e)e.preventDefault();
   var player=buildPlayer(frame);
   if(frame.dataset.lbVideoLightbox==='1'){var box=document.createElement('div');box.className='lb-lightbox-video';box.style.setProperty('--lb-video-ratio',frame.dataset.lbVideoRatio||'16 / 9');box.appendChild(player);openLightbox(box,'lb-lightbox-has-video');return}
   var existing=frame.querySelector('.lb-video-media');if(existing)existing.remove();
   frame.appendChild(player);frame.classList.add('is-playing');frame.style.backgroundImage='';
  };
  var btn=frame.querySelector('.lb-video-play');
  if(btn)btn.addEventListener('click',play);
  frame.addEventListener('click',function(e){if(e.target===frame||e.target.closest('.lb-video-play'))play(e)});
 }

 /* ---- Background slideshow ---- */
 function bgSlideshow(box){
  if(box.dataset.lbReady)return;box.dataset.lbReady='1';
  var slides=[].slice.call(box.querySelectorAll('img'));
  if(slides.length<2)return;
  var i=0,ms=Math.max(1000,parseFloat(box.getAttribute('data-duration')||5)*1000);
  var timer=setInterval(function(){
   if(document.hidden)return;
   slides[i].classList.remove('is-active');
   i=(i+1)%slides.length;
   slides[i].classList.add('is-active');
  },ms);
  box._lbSlideTimer=timer;
 }

 /* ---- Container background video (start / end offsets) ---- */
 function bgVideo(v){
  if(v.dataset.lbReady)return;v.dataset.lbReady='1';
  var start=parseFloat(v.dataset.start||0),end=parseFloat(v.dataset.end||0);
  if(start)v.addEventListener('loadedmetadata',function(){try{v.currentTime=start}catch(e){}});
  if(end)v.addEventListener('timeupdate',function(){if(v.currentTime>=end){if(v.loop){v.currentTime=start||0;v.play().catch(function(){})}else v.pause()}});
  var p=v.play&&v.play();if(p&&p.catch)p.catch(function(){});
 }

 /* ---- Motion / interactions 2.0 ---- */
 function parseFx(el){
  if(el.dataset.lbFx){
   try{var list=JSON.parse(el.dataset.lbFx);if(Array.isArray(list))return list}catch(err){}
  }
  if(el.dataset.lbInteraction){
   return [{kind:'entrance',trigger:el.dataset.lbTrigger||'viewport',effect:el.dataset.lbInteraction,duration:parseFloat(el.dataset.lbDuration||.6),delay:parseFloat(el.dataset.lbDelay||0),easing:el.style.getPropertyValue('--lb-easing')||'ease',iteration:1,repeat:el.dataset.lbRepeat==='1',threshold:parseFloat(el.dataset.lbThreshold||.15),exclude:[]}];
  }
  return [];
 }
 function fxSkip(el){
  el.classList.add('lb-fx-skip','lb-fx-play');
  el.style.opacity='';el.style.transform='';el.style.filter='';
 }
 function fxApply(el,item){
  var effect=String(item.effect||'fade').replace(/[^a-z0-9_-]/gi,'');
  var kind=item.kind||'entrance';
  el.style.setProperty('--lb-fx-duration',(parseFloat(item.duration)||.6)+'s');
  el.style.setProperty('--lb-fx-delay',(parseFloat(item.delay)||0)+'s');
  el.style.setProperty('--lb-fx-easing',item.easing||'ease');
  el.style.setProperty('--lb-fx-iteration',item.iteration==='infinite'?'infinite':String(item.iteration||1));
  el.classList.add('lb-fx');
  if(kind==='exit')el.classList.add('lb-fx-exit');else el.classList.remove('lb-fx-exit');
  if((item.trigger||'')==='scroll')el.classList.add('lb-fx-scroll');
  if(kind==='custom'||effect==='custom')el.classList.add('lb-fx-custom-0');
  else if(effect)el.classList.add('lb-fx-'+effect);
  (item.exclude||[]).forEach(function(bp){if(bp)el.classList.add('lb-fx-no-'+String(bp).replace(/[^a-z0-9_-]/gi,''))});
 }
 function fxPlay(el){
  el.classList.remove('lb-fx-play');
  void el.offsetWidth;
  el.classList.add('lb-fx-play');
 }
 function fxRewind(el){el.classList.remove('lb-fx-play')}
 function fxExcluded(item){
  var ex=item.exclude||[];
  if(!ex.length)return false;
  var w=window.innerWidth||document.documentElement.clientWidth;
  var map={mobile:w<=767,mobile_extra:w<=880,tablet:w<=1024,tablet_extra:w<=1200,laptop:w<=1366,desktop:w>1024&&w<2400,widescreen:w>=2400};
  return ex.some(function(bp){return !!map[bp]});
 }
 function bindFxItem(el,item,reduce){
  if(reduce||fxExcluded(item)){fxSkip(el);return}
  var trigger=item.trigger||'viewport';
  var kind=item.kind||'entrance';
  if(trigger==='load'){
   fxApply(el,item);requestAnimationFrame(function(){fxPlay(el)});
   return;
  }
  if(trigger==='viewport'){
   fxApply(el,item);
   if(!('IntersectionObserver' in window)){fxPlay(el);return}
   var io=new IntersectionObserver(function(entries){
    entries.forEach(function(e){
     if(e.isIntersecting){
      if(kind==='exit')fxRewind(el);else fxPlay(el);
      if(!item.repeat)io.unobserve(el);
     }else if(item.repeat){
      if(kind==='exit')fxPlay(el);else fxRewind(el);
     }
    });
   },{threshold:Math.max(0,Math.min(1,parseFloat(item.threshold)||.15))});
   io.observe(el);
   return;
  }
  if(trigger==='hover'){
   el.addEventListener('mouseenter',function(){fxApply(el,item);fxPlay(el)});
   el.addEventListener('mouseleave',function(){if(kind==='exit')fxPlay(el);else fxRewind(el)});
   return;
  }
  if(trigger==='click'){
   el.addEventListener('click',function(){
    fxApply(el,item);
    if(el.classList.contains('lb-fx-play')&&kind!=='exit')fxRewind(el);else fxPlay(el);
   });
   return;
  }
  if(trigger==='focus'){
   el.addEventListener('focus',function(){fxApply(el,item);fxPlay(el)},true);
   el.addEventListener('blur',function(){fxRewind(el)},true);
   return;
  }
  if(trigger==='scroll'){
   fxApply(el,item);
   function scrub(){
    var rect=el.getBoundingClientRect(),vh=window.innerHeight||1;
    var p=(vh-rect.top)/(vh+Math.max(1,rect.height));
    if(p<0)p=0;if(p>1)p=1;
    el.style.setProperty('--lb-fx-progress',String(p));
   }
   scrub();
   window.addEventListener('scroll',scrub,{passive:true});
   window.addEventListener('resize',scrub);
  }
 }
 function interactions(scope){
  var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var root=scope||document;
  var nodes=[].slice.call(root.querySelectorAll('[data-lb-fx],[data-lb-interaction]'));
  if(root.matches&&(root.hasAttribute('data-lb-fx')||root.hasAttribute('data-lb-interaction')))nodes.unshift(root);
  nodes.forEach(function(el){
   if(el.dataset.lbFxReady)return;el.dataset.lbFxReady='1';
   parseFx(el).forEach(function(item){bindFxItem(el,item,reduce)});
  });
 }

 /* Run counters / progress bars when they scroll into view. */
 function whenVisible(nodes,fn){
  nodes=[].slice.call(nodes);if(!nodes.length)return;
  if(!('IntersectionObserver' in window)){nodes.forEach(fn);return}
  var io=new IntersectionObserver(function(entries){entries.forEach(function(e){if(!e.isIntersecting)return;fn(e.target);io.unobserve(e.target)})},{threshold:.2});
  nodes.forEach(function(n){io.observe(n)});
 }

 function packGalleries(scope){
  scope=scope||document;
  var gals=scope.classList&&scope.classList.contains('lb-gallery')?[scope]:[].slice.call((scope.querySelectorAll?scope:document).querySelectorAll('.lb-gallery.is-justified,.lb-gallery.is-masonry'));
  gals.forEach(function(gal){
   var items=[].slice.call(gal.querySelectorAll('.lb-gallery-item')).filter(function(el){return !el.hidden});
   var W=gal.clientWidth;if(!W||!items.length)return;
   var cs=window.getComputedStyle(gal);
   var gap=parseFloat(cs.columnGap||cs.gap)||parseFloat(cs.getPropertyValue('--lb-gap'))||10;
   function ratioOf(el){
    var d=parseFloat(el.getAttribute('data-lb-ratio'));
    if(d>0.05&&d<20)return d;
    var img=el.querySelector('img');
    if(img&&img.naturalWidth&&img.naturalHeight)return img.naturalWidth/img.naturalHeight;
    return 1.5;
   }
   if(gal.classList.contains('is-justified')){
    var rh=parseFloat(cs.getPropertyValue('--lb-row-h'))||220;
    var last=gal.getAttribute('data-lb-last-row')||'auto';
    var row=[],aspect=0;
    function flush(fit){
     if(!row.length)return;
     var avail=Math.max(1,W-gap*(row.length-1));
     var sum=row.reduce(function(a,it){return a+it.r},0)||1;
     var h=fit?avail/sum:rh;
     row.forEach(function(it){
      it.el.style.width=(h*it.r)+'px';
      it.el.style.height=h+'px';
      it.el.style.flexGrow=fit?'1':'0';
      it.el.style.flexBasis=(h*it.r)+'px';
     });
     row=[];aspect=0;
    }
    items.forEach(function(el,i){
     var r=ratioOf(el);
     if(row.length&&(aspect+r)*rh+gap*row.length>W)flush(true);
     row.push({el:el,r:r});aspect+=r;
     if(i===items.length-1){
      var used=aspect*rh+gap*(row.length-1);
      flush(last==='fit'||last==='grow'||(last==='auto'&&used/W>=0.72));
     }
    });
   }else if(gal.classList.contains('is-masonry')){
    gal.style.position='';gal.style.height='';gal.style.columnCount='';gal.style.display='';
    [].slice.call(gal.querySelectorAll('.lb-gallery-item')).forEach(function(el){
     if(el.hidden||el.classList.contains('is-lb-out')){el.style.setProperty('display','none','important');return;}
     el.style.position='';el.style.left='';el.style.top='';el.style.width='';el.style.height='';el.style.margin='';el.style.display='';
    });
   }
   gal.querySelectorAll('img').forEach(function(img){
    if(img.complete)return;
    img.addEventListener('load',function(){packGalleries(gal)},{once:true});
   });
  });
 }

 function galleryFilter(host){
  if(host.dataset.lbReady)return;host.dataset.lbReady='1';
  host.addEventListener('click',function(e){
   var btn=e.target.closest('[data-lb-set]');if(!btn||!host.contains(btn))return;
   e.preventDefault();
   var key=btn.getAttribute('data-lb-set');
   host.querySelectorAll('.lb-gallery-nav [data-lb-set]').forEach(function(b){b.classList.toggle('is-active',b===btn);b.setAttribute('aria-selected',b===btn?'true':'false')});
   host.querySelectorAll('[data-lb-in]').forEach(function(item){
    var show=key===''||String(item.getAttribute('data-lb-in'))===key;
    item.hidden=!show;
    item.classList.toggle('is-lb-out',!show);
    if(!show)item.style.setProperty('display','none','important');
    else item.style.removeProperty('display');
   });
   packGalleries(host);
  });
 }

 /* ---- Flip Box (click trigger + touch fallback for hover) ---- */
 function flipBox(box){
  if(box.dataset.lbReady)return;box.dataset.lbReady='1';
  var trigger=box.getAttribute('data-lb-flip')||'hover';
  var coarse=window.matchMedia&&window.matchMedia('(hover: none)').matches;
  if(trigger!=='click'&&!coarse)return;
  function setOn(on){box.classList.toggle('is-flipped',on);if(box.getAttribute('role')==='button')box.setAttribute('aria-pressed',on?'true':'false')}
  function toggle(e){if(e.target.closest('a,button,input,textarea,select'))return;e.preventDefault();setOn(!box.classList.contains('is-flipped'))}
  box.addEventListener('click',toggle);
  box.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle(e)}});
  if(box.tabIndex<0)box.tabIndex=0;
 }

 /* ---- Collection Loop load more ---- */
 function loopMore(btn){
  if(!btn||btn.dataset.lbBusy==='1')return;
  var wrap=btn.closest('.lb-loop');
  var items=wrap?wrap.querySelector('.lb-loop-items'):null;
  var page=parseInt(btn.getAttribute('data-page')||'1',10)+1;
  var max=parseInt(btn.getAttribute('data-max')||'1',10);
  var rest=btn.getAttribute('data-rest')||(window.CanvaslyLiteFrontend&&window.CanvaslyLiteFrontend.loopRest)||'';
  var docId=btn.getAttribute('data-document')||'';
  var node=btn.getAttribute('data-node')||'';
  if(!items||!rest||!docId||!node||page>max)return;
  btn.dataset.lbBusy='1';
  btn.disabled=true;
  var url=rest+(rest.indexOf('?')>=0?'&':'?')+'document='+encodeURIComponent(docId)+'&node='+encodeURIComponent(node)+'&page='+page;
  var tax=btn.getAttribute('data-taxonomy')||'';
  var terms=btn.getAttribute('data-terms')||'';
  if(tax&&terms)url+='&taxonomy='+encodeURIComponent(tax)+'&terms='+encodeURIComponent(terms);
  fetch(url,{credentials:'same-origin'}).then(function(r){if(!r.ok)throw 0;return r.json()}).then(function(d){
   if(d&&d.html){
    var tmp=document.createElement('div');
    tmp.innerHTML=d.html;
    while(tmp.firstChild)items.appendChild(tmp.firstChild);
    init(items);
   }
   var nextMax=d&&d.max_pages?parseInt(d.max_pages,10):max;
   btn.setAttribute('data-page',String(page));
   btn.setAttribute('data-max',String(nextMax));
   if(page>=nextMax||(d&&d.done)){btn.hidden=true;return}
   btn.disabled=false;delete btn.dataset.lbBusy;
  }).catch(function(){btn.disabled=false;delete btn.dataset.lbBusy;btn.setAttribute('aria-label',t('Unable to load more items.'))});
 }

 /* ---- Lazy background images ---- */
 function lazyBg(scope){
  var root=scope||document;
  if(!root.querySelectorAll)return;
  var nodes=[].slice.call(root.querySelectorAll('[data-lb-bg]'));
  if(root.getAttribute&&root.getAttribute('data-lb-bg'))nodes.unshift(root);
  function apply(el){
   if(!el||el.getAttribute('data-lb-bg-done')==='1')return;
   var u=el.getAttribute('data-lb-bg');
   if(!u)return;
   var url='url("'+String(u).replace(/\\/g,'\\\\').replace(/"/g,'\\"')+'")';
   var target=el;
   if(el.children){
    for(var i=0;i<el.children.length;i++){
     if(el.children[i].classList&&el.children[i].classList.contains('lb-container')){target=el.children[i];break;}
    }
   }
   target.style.backgroundImage=url;
   el.classList.add('is-bg-loaded');
   el.setAttribute('data-lb-bg-done','1');
   el.removeAttribute('data-lb-bg');
  }
  if(!nodes.length)return;
  if(!('IntersectionObserver' in window)){nodes.forEach(apply);return;}
  var io=new IntersectionObserver(function(entries){
   entries.forEach(function(e){
    if(!e.isIntersecting)return;
    apply(e.target);
    io.unobserve(e.target);
   });
  },{rootMargin:'200px 0px',threshold:0.01});
  nodes.forEach(function(n){io.observe(n);});
 }

 /* ---- Handlers API ---- */
 var handlers={};
 function nodesOf(scope,type){
  var cls='lb-node-'+type,out=[];
  if(!scope)return out;
  if(scope.classList&&scope.classList.contains(cls))out.push(scope);
  if(scope.querySelectorAll){
   var found=scope.querySelectorAll('.'+cls);
   for(var i=0;i<found.length;i++)out.push(found[i]);
  }
  return out;
 }
 function within(el,selector){
  var out=[];
  if(!el)return out;
  if(el.matches&&el.matches(selector))out.push(el);
  if(el.querySelectorAll){
   var found=el.querySelectorAll(selector);
   for(var i=0;i<found.length;i++)out.push(found[i]);
  }
  return out;
 }
 function runOne(scope,type,fn){
  nodesOf(scope,type).forEach(function(el){try{fn(el,scope)}catch(e){}});
 }
 function registerHandler(type,fn){
  type=String(type||'').replace(/[^a-z0-9_-]/gi,'');
  if(!type||typeof fn!=='function')return;
  (handlers[type]=handlers[type]||[]).push(fn);
  if(document.readyState!=='loading')runOne(document,type,fn);
 }
 function drainQueue(){
  var q=(window.CanvaslyLiteFrontend&&window.CanvaslyLiteFrontend._handlersQueue)||[];
  if(window.CanvaslyLiteFrontend)window.CanvaslyLiteFrontend._handlersQueue=[];
  q.forEach(function(item){if(item)registerHandler(item.type,item.fn)});
 }
 function runHandlers(scope){
  Object.keys(handlers).forEach(function(type){
   (handlers[type]||[]).forEach(function(fn){runOne(scope,type,fn)});
  });
 }
 function bindAll(el,selector,fn){
  within(el,selector).forEach(fn);
 }
 function loopShow(el){
  var w=window.innerWidth||1024;
  var n=parseInt(el.getAttribute('data-show')||'1',10)||1;
  if(w<=767)n=parseInt(el.getAttribute('data-show-mobile')||n,10)||n;
  else if(w<=1024)n=parseInt(el.getAttribute('data-show-tablet')||n,10)||n;
  return Math.max(1,n);
 }
 function loopCarousel(el){
  if(!el||el.getAttribute('data-lb-loop-ready')==='1')return;
  var track=el.querySelector(':scope > .lb-loop-viewport > .lb-loop-track')||el.querySelector('.lb-loop-track');
  if(!track)return;
  el.setAttribute('data-lb-loop-ready','1');
  var slides=[].slice.call(track.querySelectorAll(':scope > .lb-loop-item'));
  if(!slides.length)return;
  var index=0,timer=null;
  function maxStart(){return Math.max(0,slides.length-loopShow(el));}
  function apply(){
   var show=loopShow(el),max=maxStart();
   var loop=el.getAttribute('data-loop')==='1'&&slides.length>show;
   if(loop)index=(index%(max+1)+(max+1))%(max+1);
   else index=Math.max(0,Math.min(max,index));
   var gap=parseFloat(getComputedStyle(track).columnGap)||0;
   var w=slides[0].getBoundingClientRect().width||0;
   track.style.transform='translate3d('+(-index*(w+gap))+'px,0,0)';
   var scroll=Math.max(1,parseInt(el.getAttribute('data-scroll')||'1',10)||1);
   var page=Math.round(index/scroll);
   [].slice.call(el.querySelectorAll(':scope > .lb-loop-dots > .lb-loop-dot')).forEach(function(dot,i){dot.classList.toggle('is-active',i===page);});
   var prev=el.querySelector(':scope > .lb-loop-prev'),next=el.querySelector(':scope > .lb-loop-next');
   if(prev)prev.disabled=!loop&&index===0;
   if(next)next.disabled=!loop&&index>=max;
  }
  function step(dir){index+=dir*Math.max(1,parseInt(el.getAttribute('data-scroll')||'1',10)||1);apply();}
  function halt(){if(timer){clearInterval(timer);timer=null;}}
  function start(){
   if(el.getAttribute('data-autoplay')!=='1'||reduceMotion)return;
   halt();
   timer=setInterval(function(){if(document.hidden)return;step(1);},Math.max(500,parseInt(el.getAttribute('data-interval')||'5000',10)));
  }
  el.addEventListener('click',function(e){
   var prev=e.target.closest('[data-lb-loop-dir="-1"], .lb-loop-prev');
   var next=e.target.closest('[data-lb-loop-dir="1"], .lb-loop-next');
   var dot=e.target.closest('[data-lb-loop-page]');
   if(!prev&&!next&&!dot)return;
   if(!el.contains(prev||next||dot))return;
   e.preventDefault();
   e.stopPropagation();
   if(dot){index=(parseInt(dot.getAttribute('data-lb-loop-page')||'0',10)||0)*Math.max(1,parseInt(el.getAttribute('data-scroll')||'1',10)||1);apply();halt();return;}
   step(prev?-1:1);
   halt();
  });
  if(el.getAttribute('data-pause-hover')==='1'){el.addEventListener('mouseenter',halt);el.addEventListener('mouseleave',start);}
  window.addEventListener('resize',apply);
  apply();start();
 }
 registerHandler('carousel',function(el){bindAll(el,'.lb-carousel',carousel)});
 registerHandler('collection_loop',function(el){bindAll(el,'[data-lb-loop-carousel]',loopCarousel)});
 registerHandler('tabs',function(el){bindAll(el,'.lb-tabs-widget',tabs)});
 registerHandler('nested_tabs',function(el){bindAll(el,'.lb-tabs-widget',tabs)});
 registerHandler('accordion',function(el){bindAll(el,'[data-lb-collapse]',collapse)});
 registerHandler('toggle',function(el){bindAll(el,'[data-lb-collapse]',collapse)});
 registerHandler('nested_accordion',function(el){bindAll(el,'[data-lb-collapse]',collapse)});
 registerHandler('nested_toggle',function(el){bindAll(el,'[data-lb-collapse]',collapse)});
 registerHandler('video',function(el){bindAll(el,'.lb-video-overlay[data-lb-video-src]',video)});
 registerHandler('gallery',function(el){
  bindAll(el,'[data-lb-gallery-filter-host]',galleryFilter);
  packGalleries(el);
 });
 registerHandler('flip_box',function(el){bindAll(el,'.lb-flip-box[data-lb-flip]',flipBox)});
 registerHandler('counter',function(el){whenVisible(within(el,'[data-lb-counter]'),counter)});
 registerHandler('progress',function(el){whenVisible(within(el,'[data-lb-progress]'),progress)});
 drainQueue();

 var textPathPhase={};
 function textPathSeconds(raw){var n=parseFloat(raw);return isFinite(n)&&n>0?n:20}
 function runTextPath(box){
  if(!box||box.getAttribute('data-lb-path-run')==='1')return;
  var path=box.querySelector('path'),textPath=box.querySelector('textPath');
  if(!path||!textPath||typeof path.getTotalLength!=='function')return;
  var text=textPath.parentNode;
  if(reduceMotion){textPath.setAttribute('startOffset','0');if(text)text.style.visibility='visible';return}
  box.setAttribute('data-lb-path-run','1');
  var key=path.id||'tp';
  var phase=textPathPhase[key]||(textPathPhase[key]={pos:0,last:0,len:0});
  phase.len=0;
  var view=box.ownerDocument.defaultView||window;
  if(text)text.style.visibility='hidden';
  function measure(){
   var svg=path.ownerSVGElement;
   if(!svg||!text)return phase.len||0;
   var probe=text.cloneNode(false);
   probe.textContent=textPath.textContent||'';
   probe.removeAttribute('visibility');
   probe.style.visibility='visible';
   probe.setAttribute('x','0');
   probe.setAttribute('y','20');
   svg.appendChild(probe);
   var n=0;
   try{n=probe.getComputedTextLength()||0}catch(err){n=0}
   svg.removeChild(probe);
   if(n>1)phase.len=n;
   return phase.len||0;
  }
  var tries=0;
  function step(now){
   if(!box.isConnected)return;
   if(!phase.last)phase.last=now;
   var dt=Math.min(80,Math.max(0,now-phase.last));
   phase.last=now;
   var pathLen=0;
   try{pathLen=path.getTotalLength()||0}catch(err){pathLen=0}
   var textLen=phase.len>1?phase.len:measure();
   if(pathLen>1&&textLen>1){
    var span=pathLen+textLen;
    var secs=textPathSeconds(view.getComputedStyle(box).getPropertyValue('--lb-speed')||box.getAttribute('data-lb-speed'));
    phase.pos=(phase.pos+dt/(secs*1000))%1;
    textPath.setAttribute('startOffset',String(-textLen+phase.pos*span));
    if(text)text.style.visibility='visible';
   }else if(++tries>45){
    textPath.setAttribute('startOffset','0');
    if(text)text.style.visibility='visible';
   }
   view.requestAnimationFrame(step);
  }
  var fonts=view.document&&view.document.fonts;
  if(fonts&&fonts.ready)fonts.ready.then(function(){measure()});
  view.requestAnimationFrame(step);
 }
 function textPaths(scope){
  var root=scope&&scope.querySelectorAll?scope:document,list=[];
  if(root.classList&&root.classList.contains('lb-text-path'))list.push(root);
  if(root.querySelectorAll)[].slice.call(root.querySelectorAll('.lb-text-path')).forEach(function(el){if(list.indexOf(el)<0)list.push(el)});
  list.forEach(runTextPath);
 }

 function init(scope){
  scope=scope||document;
  drainQueue();
  runHandlers(scope);
  textPaths(scope);
  if(scope.querySelectorAll){
   scope.querySelectorAll('video[data-lb-bg-video]').forEach(bgVideo);
   scope.querySelectorAll('[data-lb-bg-slideshow]').forEach(bgSlideshow);
  }
  interactions(scope);
  lazyBg(scope);
 }

 document.addEventListener('DOMContentLoaded',function(){
  init(document);alerts();interactions();
  window.addEventListener('resize',function(){packGalleries(document)});
  document.addEventListener('click',function(e){var more=e.target.closest('[data-lb-loop-more]');if(more){e.preventDefault();loopMore(more);return}var a=e.target.closest('.lb-gallery[data-lightbox="1"] a,[data-lb-lightbox="1"]');if(!a||!a.href)return;e.preventDefault();imageLightbox(a)});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.lb-lightbox').forEach(function(x){x.remove()})});
 });
 window.CanvaslyLiteFrontend=Object.assign(window.CanvaslyLiteFrontend||{},{init:init,registerHandler:registerHandler});
})();

/* Form submissions */
(function(){
 var I18N=(window.CanvaslyLiteFrontend&&window.CanvaslyLiteFrontend.i18n)||{};
 function t(key){return Object.prototype.hasOwnProperty.call(I18N,key)?String(I18N[key]):String(key)}
 function recaptchaToken(f,done){
  var box=f.querySelector('[data-lb-recaptcha]');
  if(!box){done('');return}
  var type=box.getAttribute('data-lb-recaptcha')||'v2';
  var key=box.getAttribute('data-sitekey')||'';
  var existing=f.querySelector('[name="g-recaptcha-response"]');
  if(existing&&existing.value){done(existing.value);return}
  if(!window.grecaptcha){done('');return}
  if(type==='v3'&&key&&grecaptcha.execute){
   var run=function(){grecaptcha.execute(key,{action:'canvasly_lite_form'}).then(function(token){done(token||'')}).catch(function(){done('')})};
   if(grecaptcha.ready)grecaptcha.ready(run);else run();
   return;
  }
  done(existing?existing.value:'');
 }
 function renderRecaptcha(root){
  if(!window.grecaptcha||!grecaptcha.render)return;
  (root||document).querySelectorAll('[data-lb-recaptcha="v2"]').forEach(function(el){
   if(el.getAttribute('data-lb-rendered'))return;
   var key=el.getAttribute('data-sitekey')||'';
   if(!key)return;
   try{grecaptcha.render(el,{sitekey:key});el.setAttribute('data-lb-rendered','1')}catch(e){}
  });
 }
 document.addEventListener('submit',function(e){
  var f=e.target.closest('.lb-form[data-lb-form]');
  if(!f)return;
  e.preventDefault();
  var hp=f.querySelector('[name="website"]');
  var msg=f.querySelector('.lb-form-message');
  if(hp&&hp.value){if(msg)msg.textContent='';return}
  recaptchaToken(f,function(token){
   var fd=new FormData(f);
   fd.delete('website');
   if(token)fd.set('g-recaptcha-response',token);
   fetch((window.CanvaslyLiteFrontend&&CanvaslyLiteFrontend.formEndpoint)||window.canvaslyLiteFormEndpoint||window.location.href,{method:'POST',body:fd,headers:{'X-Canvasly-Lite-Form':'1'}}).then(function(r){return r.ok?r.json():Promise.reject()}).then(function(d){if(d&&d.success&&typeof d.redirect==='string'&&/^https?:\/\//i.test(d.redirect)){window.location.assign(d.redirect);return}if(msg)msg.textContent=(d&&d.message)||t('Thank you.');if(d&&d.success)f.reset();if(window.grecaptcha&&grecaptcha.reset){try{grecaptcha.reset()}catch(err){}}}).catch(function(){if(msg)msg.textContent=t('Unable to send the form right now.')});
  });
 });
 function bootRecaptcha(){
  if(!document.querySelector('[data-lb-recaptcha]'))return;
  if(window.grecaptcha&&grecaptcha.render){renderRecaptcha(document);return}
  var n=0,timer=setInterval(function(){n++;if(window.grecaptcha&&grecaptcha.render){clearInterval(timer);renderRecaptcha(document)}if(n>40)clearInterval(timer)},250);
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',bootRecaptcha);else bootRecaptcha();
 if(window.CanvaslyLiteFrontend){
  var prev=window.CanvaslyLiteFrontend.init;
  window.CanvaslyLiteFrontend.init=function(root){if(typeof prev==='function')prev(root);renderRecaptcha(root||document)};
 }
})();
