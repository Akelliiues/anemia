(function () {
 const button = document.querySelector('[data-report-pdf]');
 if (!button) return;

 const paper = document.querySelector('.report-paper');
 const notice = document.getElementById('report-pdf-hint');
 const landscape = document.body.classList.contains('report-landscape');
 const base = new URL('.', document.currentScript.src);
 const root = new URL('../', base);
 function contentText(element) {
  return element.textContent.trim();
 }

 function convertTable(element) {
  element = element.matches('table') ? element : element.querySelector('table');
  const body = Array.from(element.rows, () => []);
  Array.from(element.rows).forEach((row, rowIndex) => {
   let column = 0;
   for (const cell of row.cells) {
    while (body[rowIndex][column] !== undefined) column++;
    const heading = row.parentElement.tagName === 'THEAD';
    body[rowIndex][column] = {
     text: contentText(cell),
     bold: cell.tagName === 'TH',
     alignment: heading || cell.tagName === 'TD' ? 'center' : 'left',
     ...(heading ? {fillColor:'#f0eef5'} : {}),
     ...(cell.colSpan > 1 ? {colSpan:cell.colSpan} : {}),
     ...(cell.rowSpan > 1 ? {rowSpan:cell.rowSpan} : {})
    };
    for (let y = rowIndex; y < rowIndex + cell.rowSpan; y++) {
     for (let x = column; x < column + cell.colSpan; x++) {
      if (y !== rowIndex || x !== column) body[y][x] = {};
     }
    }
    column += cell.colSpan;
   }
  });
  const widths = element.classList.contains('report-province-table')
   ? [80, 55, 55, 55, 58, 55, 58, 58, 58, 58, 78]
   : body[0].map(() => '*');
  return {
   fontSize: landscape ? 13 : 14,
   table: {headerRows: element.tHead?.rows.length || 0, widths, body, dontBreakRows:true},
   layout: {
    hLineColor: () => '#77747c',
    vLineColor: () => '#77747c'
   }
  };
 }

 function createDefinition(logo) {
  const footer = paper.querySelector('.report-paper-footer');
  const content = Array.from(paper.children).filter(element => element !== footer).map(element => {
   switch (element.tagName) {
    case 'HEADER':
     return {alignment:'center', margin:[0,0,0,12], stack:Array.from(element.children, line => ({
      text:contentText(line), ...(line.tagName === 'H1' ? {fontSize:24,bold:true} : {})
     }))};
    case 'DIV':
    case 'TABLE':
     return convertTable(element);
    case 'H2':
     return {text:contentText(element), bold:true, fontSize:19, margin:[0,10,0,4], headlineLevel:1};
    case 'UL':
     return {ul:Array.from(element.children, contentText)};
    default:
     return {text:contentText(element),fontSize:14,margin:[0,5,0,0]};
   }
  });
  const margin = landscape ? 34 : 37;
  return {
   footer:{
    margin:[margin, margin - 34, margin, 0],
    columns:[
     {image:'brand',width:22},
     {stack:Array.from(footer.querySelectorAll('p'), line => ({text:contentText(line),color:'#737078',fontSize:10,lineHeight:0.83}))}
    ],
    columnGap:5
   },
   pageSize:'A4',
   pageOrientation:landscape ? 'landscape' : 'portrait',
   pageMargins:margin,
   info:{title:paper.querySelector('h1').textContent.trim()},
   defaultStyle:{font:'Sarabun',fontSize:16},
   images:{brand:logo},
   pageBreakBefore:(node, following) => node.headlineLevel === 1 && following.length === 0,
   content
  };
 }

 async function fetchDataUrl(url) {
  const response = await fetch(url);
  if (!response.ok) throw new Error('Report asset unavailable');
  const blob = await response.blob();
  return new Promise((resolve, reject) => {
   const reader = new FileReader();
   reader.onload = () => resolve(reader.result);
   reader.onerror = reject;
   reader.readAsDataURL(blob);
  });
 }

 let engine;
 button.addEventListener('click', async () => {
  button.disabled = true;
  notice.hidden = false;
  notice.textContent = 'กำลังจัดทำไฟล์ PDF…';
  try {
   engine ||= new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = new URL('pdfmake.min.js', base).href;
    script.onload = resolve;
    script.onerror = reject;
    document.head.append(script);
   }).catch(error => { engine = undefined; throw error; });
   await engine;
   const [regular, bold, logo] = await Promise.all([
    fetchDataUrl(new URL('report-fonts/THSarabunNew.ttf',root)),
    fetchDataUrl(new URL('report-fonts/THSarabunNew%20Bold.ttf',root)),
    fetchDataUrl(paper.querySelector('.report-paper-footer img').src)
   ]);
   const pdf = window.pdfMake.createPdf(createDefinition(logo), undefined, {
    Sarabun:{normal:'regular.ttf',bold:'bold.ttf',italics:'regular.ttf',bolditalics:'bold.ttf'}
   }, {
    'regular.ttf':regular.split(',')[1],
    'bold.ttf':bold.split(',')[1]
   });
   const date = new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Bangkok'}).format();
   const title = paper.querySelector('h1').textContent.trim();
   await new Promise(resolve => pdf.getBlob(resolve)).then(blob => {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = title + '-' + date + '.pdf';
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
   });
   notice.hidden = true;
  } catch {
   notice.textContent = 'จัดทำไฟล์ PDF ไม่สำเร็จ ลองกดบันทึกอีกครั้ง';
  } finally {
   button.disabled = false;
  }
 });
})();
