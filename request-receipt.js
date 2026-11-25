const receiptPattern=/^(?:AN|DE)-[0-9A-F]{16}$/;
const receiptStates={pending:'รอประสาน',contacted:'เจ้าหน้าที่ติดต่อแล้ว',closed:'ปิดคำขอแล้ว'};

function receiptStatusUrl(receipt){
 const url=new URL(location.pathname,location.origin);
 url.hash='request='+receipt;
 return url.href;
}

function makeReceiptQr(receipt){
 const qr=qrcode(0,'M');
 qr.addData(receiptStatusUrl(receipt));
 qr.make();
 return qr;
}

async function drawRequestReceipt(canvas,{receipt,facility,kind,sample}){
 await document.fonts.load('700 42px Anuphan');
 await document.fonts.ready;
 if(!canvas.isConnected)return;
 const ctx=canvas.getContext('2d');
 const qr=makeReceiptQr(receipt);
 const modules=qr.getModuleCount();
 const scale=Math.max(1,Math.floor(280/(modules+8)));
 const size=(modules+8)*scale;
 const gradient=ctx.createLinearGradient(0,0,720,420);
 gradient.addColorStop(0,'#332252');
 gradient.addColorStop(1,'#6948a1');
 ctx.fillStyle='#fbf8fe';
 ctx.fillRect(0,0,720,840);
 ctx.fillStyle=gradient;
 ctx.fillRect(0,0,720,210);
 ctx.fillStyle='#ffffff';
 ctx.font='700 28px Anuphan, sans-serif';
 ctx.fillText('Anemia Portal',45,63);
 ctx.font='700 41px Anuphan, sans-serif';
 ctx.fillText(sample?'ตัวอย่างใบรับคำขอ':'ใบรับคำขอ',45,121);
 ctx.font='500 26px Anuphan, sans-serif';
 ctx.fillText(kind==='appointment'?'คำขอนัดเข้าตรวจ':'คำขอให้ติดต่อกลับ',45,170);
 ctx.fillStyle='#342450';
 ctx.font='650 30px Anuphan, sans-serif';
 const segmenter=new Intl.Segmenter('th',{granularity:'grapheme'});
 let line='',y=259;
 for(const {segment} of segmenter.segment(facility)){
  if(line&&ctx.measureText(line+segment).width>630){ctx.fillText(line,45,y);y+=37;line='';}
  line+=segment;
 }
 if(line)ctx.fillText(line,45,y);
 ctx.fillStyle='#614496';
 ctx.font='700 32px Anuphan, sans-serif';
 ctx.fillText(receipt,45,365);
 const x=Math.round((720-size)/2),top=395;
 ctx.fillStyle='#fff';ctx.fillRect(x,top,size,size);
 ctx.fillStyle='#161020';
 for(let row=0;row<modules;row++){
  for(let col=0;col<modules;col++){
   if(qr.isDark(row,col))ctx.fillRect(x+(col+4)*scale,top+(row+4)*scale,scale,scale);
  }
 }
 ctx.textAlign='center';
 ctx.fillStyle='#523879';
 ctx.font='650 26px Anuphan, sans-serif';
 ctx.fillText('สแกน QR เพื่อติดตามสถานะ',360,724);
 ctx.fillStyle='#635473';
 ctx.font='500 22px Anuphan, sans-serif';
 ctx.fillText(sample?'ตัวอย่างสำหรับทดลองใช้งาน':'ใช้เลขคำขอนี้เมื่อติดต่อเจ้าหน้าที่',360,771);
 ctx.fillText('วันนัดต้องรอเจ้าหน้าที่ยืนยัน',360,805);
}
