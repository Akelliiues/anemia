(() => {
 const key = 'anemia-theme';
 const system = matchMedia('(prefers-color-scheme: dark)');
 const root = document.documentElement;
 let preference;
 let control;
 try { preference = localStorage.getItem(key) || localStorage.getItem('anemia-feedback-theme'); } catch {}
 const apply = () => {
  const mode = ['light','dark'].includes(preference) ? preference : system.matches ? 'dark' : 'light';
  root.dataset.anemiaTheme = mode;
  if (control) control.setAttribute('aria-checked',String(mode === 'dark'));
 };
 apply();
 system.addEventListener('change',apply);
 window.addEventListener('storage', event => {
  if (event.key !== key) return;
  preference = event.newValue;
  apply();
 });
 document.addEventListener('DOMContentLoaded', () => {
  const reducedMotion=matchMedia('(prefers-reduced-motion: reduce)');
  const nodes=[[0,210],[115,160],[225,255],[340,135],[445,215],[560,96],[660,170],[765,82],[880,160],[1000,95],[680,310],[805,270],[940,350],[430,380],[180,355],[0,390],[550,285],[1000,240],[850,390],[725,390],[330,43],[115,72],[510,0],[970,14]];
  const routes=[[0,1,2,3,4,5,6,7,8,9],[15,14,2,4,16,10,11,17],[21,1,3,20,22,5,7,23],[14,13,16,6,11,8,17],[13,10,19,18,12,17],[5,16,11,12],[20,4,13],[7,11,18],[1,14],[3,5,8,12],[2,13],[6,10]];
  const traces=routes.map(route=>{
   const samples=[];
   route.slice(1).forEach((end,index)=>{
    const [x,y]=nodes[route[index]], [u,v]=nodes[end];
    for(let step=index===0?0:1;step<=12;step++){
     const t=step/12, bend=Math.sin(Math.PI*t)*((index%2?-1:1)*14);
     samples.push([x+(u-x)*t,y+(v-y)*t+bend]);
    }
   });
   return samples;
  });
  function paint(scene,time){
   const {canvas,context,width,height,dark,header}=scene;
   if(!width||!height)return;
   context.clearRect(0,0,canvas.width,canvas.height);
   const columns=Math.ceil(width/460), rows=Math.ceil(height/290);
   const tileWidth=canvas.width/columns, tileHeight=canvas.height/rows;
   for(let row=0;row<rows;row++){
    for(let column=0;column<columns;column++){
     context.save();
     context.translate(column*tileWidth,row*tileHeight);
     context.scale(tileWidth/1000,tileHeight/400);
     if((row+column)%2){context.translate(1000,0);context.scale(-1,1);}
     if(row%2){context.translate(0,400);context.scale(1,-1);}
     context.lineWidth=1.4;
     context.strokeStyle=header?'#cab6ff50':dark?'#91c8ff75':'#7353ad59';
     for(const samples of traces){
      context.beginPath();
      samples.forEach(([x,y],i)=>i?context.lineTo(x,y):context.moveTo(x,y));
      context.stroke();
     }
     function dot([x,y],radius){context.beginPath();context.arc(x,y,radius,0,2*Math.PI);context.fill();}
     context.fillStyle=header?'#dbc9ff78':dark?'#b7ddf8a0':'#8650b988';
     nodes.forEach(node=>dot(node,3));
     if(!reducedMotion.matches){
      for(const [i,samples] of traces.entries()){
       if((i+row+column)%3!==0)continue;
       const position=((time/(3700+i*520)+i*.17+row*.23+column*.31)%1)*(samples.length+8);
       context.fillStyle=header?(i%3===0?'#e9bbff':'#a8e5ff'):i%3===0?(dark?'#ffb7f4':'#ad459c'):(dark?'#c6f3ff':'#4c64bd');
       context.shadowColor=context.fillStyle;
       context.shadowBlur=3;
       for(let tail=0;tail<5;tail++){
        const offset=position-tail;
        const point=samples[Math.floor(offset)];
        if(!point)continue;
        const next=samples[Math.ceil(offset)]||point;
        const fraction=offset-Math.floor(offset);
        context.globalAlpha=1-tail*.17;
        dot([point[0]+(next[0]-point[0])*fraction,point[1]+(next[1]-point[1])*fraction],tail===0?3.3:1.7);
       }
      }
     }
     context.restore();
    }
   }
  }
  const scenes=[];
  function addScene(parent,header=false){
   const canvas=document.createElement('canvas');
   const context=canvas.getContext('2d');
   if(!context)return;
   const scene={canvas,context,header};
   canvas.className='portal-neural '+(header?'portal-neural-header':'portal-neural-page');
   canvas.setAttribute('aria-hidden','true');
   parent.append(canvas);
   scenes.push(scene);
   new ResizeObserver(([{contentRect:{width,height}}])=>{
    const ratio=Math.min(devicePixelRatio||1,1.5);
    scene.width=width;scene.height=height;
    canvas.width=Math.round(width*ratio);canvas.height=Math.round(height*ratio);
    refreshScene(scene,performance.now());
   }).observe(canvas);
  }
  function refreshScene(scene,time){
   scene.dark=root.dataset.anemiaTheme==='dark';
   paint(scene,time);
  }
  function refresh(){
   const time=performance.now();
   scenes.forEach(scene=>refreshScene(scene,time));
  }
  addScene(document.body);
  const headerArt=document.querySelector('.workspace-header-art');
  if(headerArt)addScene(headerArt,true);
  let last=0,frame=0;
  function animate(time){
   frame=0;
   if(document.hidden||reducedMotion.matches)return;
   if(time-last>=40){scenes.forEach(scene=>paint(scene,time));last=time;}
   startMotion();
  }
  function startMotion(){
   if(scenes.length&&!frame&&!document.hidden&&!reducedMotion.matches)frame=requestAnimationFrame(animate);
  }
  document.addEventListener('visibilitychange',startMotion);
  reducedMotion.addEventListener('change',()=>{refresh();startMotion();});
  new MutationObserver(refresh).observe(root,{attributes:true,attributeFilter:['data-anemia-theme']});
  startMotion();
  control = document.createElement('button');
  control.type = 'button';
  control.className = 'theme-float';
  control.setAttribute('role','switch');
  control.setAttribute('aria-label','โหมดมืด');
  control.title = 'สลับโหมดสว่าง–มืด';
  control.innerHTML = '<span class="theme-thumb" aria-hidden="true"></span><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 15A9 9 0 0 1 9 4a9 9 0 1 0 11 11Z"/></svg>';
  control.addEventListener('click', () => {
   preference = root.dataset.anemiaTheme === 'dark' ? 'light' : 'dark';
   try { localStorage.setItem(key,preference); } catch {}
   apply();
  });
  document.body.append(control);
  apply();
 },{once:true});
})();
