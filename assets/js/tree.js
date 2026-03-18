/* =============================================
   FILE: assets/js/tree.js
   MAQSAD: Shajara tizimining barcha dinamik funksiyalari
   ============================================= */

var FOTO_PATH = 'assets/uploads/';
var CURRENT_ESLATMA_ITEMS = [];
var CURRENT_ESLATMA_FILTER = 'all';
var CURRENT_TREE_RAW = null;
var CURRENT_SPOUSES = [];
var _collapsedMap = {};

var _svg = null,
    _g = null,
    _zoom = null,
    _allNodes = null,
    _allLinks = null,
    _allSpouses = [],
    _nodeMap = {},
    _tanlangan = null,
    _vafotFilter = false;

var _minimapSvg = null,
    _minimapViewRect = null,
    _minimapScale = 1,
    _minimapTransform = {x: 0, y: 0};

var NW = 148, NH = 192, FR = 27;
var SW = 118, SH = 164, SFR = 21;
var GAP = 20;

var FAMILY_BASE_GAP = 16;
var FAMILY_SPOUSE_GAP = 44;
var FAMILY_BIG_GAP = 56;
var LEVEL_GAP = 56;

var LINK_COLORS = ['#667eea','#e67e22','#27ae60','#e74c3c','#8e44ad','#16a085','#d35400','#2980b9','#c0392b','#1abc9c'];
var _charts = {};

/* =========================
   YORDAMCHI FUNKSIYALAR
   ========================= */
function fotoUrl(f){
    if(!f) return null;
    if(f.startsWith('http') || f.startsWith('/') || f.startsWith('assets/')) return f;
    return FOTO_PATH + f;
}

function _decode(s){
    if(!s) return '';
    var el = document.createElement('textarea');
    el.innerHTML = s;
    let val = el.value;
    el.innerHTML = val; val = el.value;
    el.innerHTML = val; val = el.value;
    el.innerHTML = val; val = el.value;
    return val;
}

function _escapeHtml(str){
    return String(str || '').replace(/[&<>"']/g, function(m){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
    });
}
function _sanaf(s){
    if(!s) return '';
    try{
        var q = String(s).split('-');
        return (q[2] || '??') + '.' + (q[1] || '??') + '.' + q[0];
    }catch(e){
        return s;
    }
}
function _yosh(s, refDate){
    if(!s) return null;
    try{
        var t = new Date(s);
        var b = refDate ? new Date(refDate) : new Date();
        var y = b.getFullYear() - t.getFullYear();
        if (b.getMonth() < t.getMonth() || (b.getMonth() === t.getMonth() && b.getDate() < t.getDate())) y--;
        return y >= 0 ? y : null;
    }catch(e){
        return null;
    }
}
function _tr(s, max){
    if(!s) return '';
    s = String(s);
    return s.length > max ? s.slice(0, max) + '…' : s;
}

function _ageLabel(data){
    if (!data) return '';
    var currentAge = _yosh(data.tugilgan_sana);

    if (+data.tirik) {
        return currentAge !== null ? currentAge + ' yosh' : '';
    }

    if (data.vafot_sana) {
        var deathAge = _yosh(data.tugilgan_sana, data.vafot_sana);
        var nowAge = _yosh(data.tugilgan_sana);
        if (deathAge !== null && nowAge !== null) return deathAge + ' yosh (' + nowAge + ')';
        if (deathAge !== null) return deathAge + ' yosh';
    }

    return currentAge !== null ? currentAge + ' yosh' : '';
}

function splitNameLines(fullName, maxCharsPerLine, maxLines){
    var words = String(fullName || '').trim().split(/\s+/).filter(Boolean);
    if (!words.length) return [''];

    var lines = [];
    var current = '';

    words.forEach(function(word){
        var test = current ? (current + ' ' + word) : word;
        if (test.length <= maxCharsPerLine) {
            current = test;
        } else {
            if (current) lines.push(current);
            current = word;
        }
    });

    if (current) lines.push(current);
    if (lines.length <= maxLines) return lines;

    var kept = lines.slice(0, maxLines);
    kept[maxLines - 1] = kept[maxLines - 1] + '…';
    return kept;
}

function getMainNameLines(data){
    return splitNameLines(((data.ism || '') + ' ' + (data.familiya || '')).trim(), 14, 3);
}

function getSpouseNameLines(data){
    return splitNameLines(((data.ism || '') + ' ' + (data.familiya || '')).trim(), 12, 3);
}

function getMainCardHeight(data){
    var lines = getMainNameLines(data);
    return 174 + Math.max(0, lines.length - 1) * 14;
}

function getSpouseCardHeight(data){
    var lines = getSpouseNameLines(data);
    return 150 + Math.max(0, lines.length - 1) * 12;
}

function appendMultiLineText(parent, opts){
    var lines = opts.lines || [''];
    var x = opts.x || 0;
    var y = opts.y || 0;
    var lineHeight = opts.lineHeight || 12;
    var fontSize = opts.fontSize || '11px';
    var fill = opts.fill || '#333';
    var weight = opts.weight || '600';

    var t = parent.append('text')
        .attr('x', x)
        .attr('y', y)
        .attr('text-anchor', 'middle')
        .style('font-size', fontSize)
        .style('font-weight', weight)
        .style('fill', fill);

    lines.forEach(function(line, idx){
        t.append('tspan')
            .attr('x', x)
            .attr('dy', idx === 0 ? 0 : lineHeight)
            .text(line);
    });

    return t;
}

/* =========================
   TEMA
   ========================= */
function toggleTheme(){
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('oila_theme_dark', document.body.classList.contains('dark-mode') ? '1' : '0');
}
(function initTheme(){
    if(localStorage.getItem('oila_theme_dark') === '1'){
        document.addEventListener('DOMContentLoaded', function(){
            document.body.classList.add('dark-mode');
        });
    }
})();

/* =========================
   PDF EXPORT
   ========================= */
function closeAllModalsForExport(){
    document.querySelectorAll('.umodal.active,.shaxsmodal.active').forEach(function(el){
        el.classList.remove('active');
    });
    document.body.style.overflow = '';
}
async function waitForImagesInElement(el){
    const images = Array.from(el.querySelectorAll('img'));
    const svgImages = Array.from(el.querySelectorAll('image'));

    const htmlImgPromises = images.map(img => new Promise(resolve => {
        if (img.complete) return resolve();
        img.onload = () => resolve();
        img.onerror = () => resolve();
    }));

    const svgImgPromises = svgImages.map(img => new Promise(resolve => {
        const href = img.getAttribute('href') || img.getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        if (!href) return resolve();
        const testImg = new Image();
        testImg.crossOrigin = 'anonymous';
        testImg.onload = () => resolve();
        testImg.onerror = () => resolve();
        testImg.src = href;
    }));

    await Promise.all([...htmlImgPromises, ...svgImgPromises]);
}
async function exportTreeToPDF(){
    try{
        closeAllModalsForExport();

        const diagram = document.getElementById('shajaraDiagram');
        d3.select('.minimap-container').style('display', 'none');

        const oldOverflow = diagram.style.overflow;
        diagram.style.overflow = 'visible';

        const hadSelection = _tanlangan;
        tanlashniTozala();
        resetZoom(0);

        await new Promise(r => setTimeout(r, 900));
        await waitForImagesInElement(diagram);

        const canvas = await html2canvas(diagram, {
            scale: 2,
            useCORS: true,
            allowTaint: false,
            backgroundColor: null,
            width: diagram.scrollWidth,
            height: diagram.scrollHeight,
            windowWidth: diagram.scrollWidth,
            windowHeight: diagram.scrollHeight
        });

        diagram.style.overflow = oldOverflow;
        d3.select('.minimap-container').style('display', '');

        if(hadSelection && _nodeMap[hadSelection]) nodeClick(_nodeMap[hadSelection]);

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('l', 'mm', 'a2');
        const imgData = canvas.toDataURL('image/png');

        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 8;
        const usableWidth = pageWidth - margin * 2;
        const usableHeight = pageHeight - margin * 2;

        const imgWidth = usableWidth;
        const imgHeight = canvas.height * imgWidth / canvas.width;

        let heightLeft = imgHeight;
        let position = margin;

        pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
        heightLeft -= usableHeight;

        while(heightLeft > 0){
            position = heightLeft - imgHeight + margin;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', margin, position, imgWidth, imgHeight);
            heightLeft -= usableHeight;
        }

        pdf.save('oila-shajarasi.pdf');
    }catch(e){
        console.error(e);
        alert('PDF eksportda xatolik yuz berdi');
        d3.select('.minimap-container').style('display', '');
    }
}

/* =========================
   MODALLAR
   ========================= */
function openModal(id){
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
    if(id === 'statModal') setTimeout(buildCharts, 100);
    if(id === 'eslatmaModal') eslatmalarYukla();
}
function closeModal(id){
    document.getElementById(id).classList.remove('active');
    if(!document.querySelector('.umodal.active') && !document.querySelector('.shaxsmodal.active') && !document.getElementById('shajaraLightbox')) {
        document.body.style.overflow = '';
    }
}
function backdropClose(ev, id){
    if(ev.target === document.getElementById(id)) closeModal(id);
}

/* =========================
   CHARTS
   ========================= */
const chartValuePlugin = {
    id: 'chartValuePlugin',
    afterDatasetsDraw(chart) {
        const {ctx} = chart;
        const dataset = chart.data.datasets[0];
        if (!dataset) return;

        const total = (dataset.data || []).reduce((a, b) => a + Number(b || 0), 0);

        chart.getDatasetMeta(0).data.forEach((element, index) => {
            const value = Number(dataset.data[index] || 0);
            if (!value) return;

            ctx.save();
            ctx.font = '700 11px Segoe UI';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            if (chart.config.type === 'bar') {
                ctx.fillStyle = '#2c3e50';
                ctx.fillText(String(value), element.x, element.y - 10);
            } else {
                const percent = total ? Math.round((value / total) * 100) : 0;
                const pos = element.tooltipPosition ? element.tooltipPosition() : {x: element.x, y: element.y};
                ctx.fillStyle = '#ffffff';
                ctx.fillText(value + ' (' + percent + '%)', pos.x, pos.y);
            }
            ctx.restore();
        });
    }
};
Chart.register(chartValuePlugin);

function rebuild(id, fn){
    if(_charts[id]) _charts[id].destroy();
    var el = document.getElementById(id);
    if(!el) return;
    _charts[id] = fn(el.getContext('2d'));
}
function getGenerationCounts(raw){
    var counts = {};
    function walk(node, depth){
        if (!node) return;
        counts[depth] = (counts[depth] || 0) + 1;
        var ch = node.children || node.farzandlar || [];
        ch.forEach(function(c){ walk(c, depth + 1); });
    }
    if (raw) walk(raw, 1);

    var labels = Object.keys(counts).sort(function(a,b){ return a-b; }).map(function(k){ return k + '-avlod'; });
    var vals = Object.keys(counts).sort(function(a,b){ return a-b; }).map(function(k){ return counts[k]; });
    return {labels: labels, vals: vals};
}
function buildCharts(){
    rebuild('chartHolat', function(ctx){
        return new Chart(ctx,{
            type:'doughnut',
            data:{
                labels:['Tirik','Vafot etgan'],
                datasets:[{
                    data:[window.PHP_STATS.tirik, window.PHP_STATS.vafot],
                    backgroundColor:['#48c78e','#d98aa0'],
                    borderWidth:2,
                    borderColor:'#fff',
                    hoverOffset:6
                }]
            },
            options:{
                responsive:true,
                plugins:{
                    legend:{ position:'bottom', labels:{font:{size:11},boxWidth:11,padding:10} },
                    tooltip:{
                        callbacks:{
                            label:function(c){
                                const total = window.PHP_STATS.tirik + window.PHP_STATS.vafot;
                                const percent = total ? Math.round((c.raw/total)*100) : 0;
                                return c.label + ': ' + c.raw + ' ta (' + percent + '%)';
                            }
                        }
                    }
                },
                cutout:'56%'
            }
        });
    });

    rebuild('chartJins', function(ctx){
        return new Chart(ctx,{
            type:'pie',
            data:{
                labels:['Erkak','Ayol'],
                datasets:[{
                    data:[window.PHP_STATS.erkak, window.PHP_STATS.ayol],
                    backgroundColor:['#5c6bc0','#f06292'],
                    borderWidth:2,
                    borderColor:'#fff',
                    hoverOffset:6
                }]
            },
            options:{
                responsive:true,
                plugins:{
                    legend:{ position:'bottom', labels:{font:{size:11},boxWidth:11,padding:10} },
                    tooltip:{
                        callbacks:{
                            label:function(c){
                                const total = window.PHP_STATS.erkak + window.PHP_STATS.ayol;
                                const percent = total ? Math.round((c.raw/total)*100) : 0;
                                return c.label + ': ' + c.raw + ' ta (' + percent + '%)';
                            }
                        }
                    }
                }
            }
        });
    });

    rebuild('chartAvlod', function(ctx){
        var gen = getGenerationCounts(CURRENT_TREE_RAW);
        return new Chart(ctx,{
            type:'bar',
            data:{
                labels:gen.labels.length ? gen.labels : ['1-avlod'],
                datasets:[{
                    label:"A'zolar",
                    data:gen.vals.length ? gen.vals : [0],
                    backgroundColor:['#667eea','#48c78e','#f5b042','#f45656','#9b59b6','#16a085','#e67e22','#2980b9'],
                    borderRadius:7,
                    borderSkipped:false
                }]
            },
            options:{
                responsive:true,
                plugins:{
                    legend:{display:false},
                    tooltip:{
                        callbacks:{
                            label:function(c){
                                const total = (gen.vals || []).reduce((a,b)=>a+b,0);
                                const percent = total ? Math.round((c.raw/total)*100) : 0;
                                return c.raw + " ta (" + percent + "%)";
                            }
                        }
                    }
                },
                scales:{
                    y:{beginAtZero:true,grid:{color:'#f0f2f5'},ticks:{font:{size:10}, precision:0}},
                    x:{grid:{display:false},ticks:{font:{size:10}}}
                }
            }
        });
    });
}

/* =========================
   ESLATMALAR
   ========================= */
function setEslatmaFilter(filter, btn){
    CURRENT_ESLATMA_FILTER = filter;
    document.querySelectorAll('.eslatma-filter-btn').forEach(function(b){ b.classList.remove('active'); });
    if(btn) btn.classList.add('active');
    renderEslatmalar();
}
function getBirthdayVisual(e){
    var foto = fotoUrl(e.foto || e.photo || null);
    if(foto){
        return '<div class="eslatma-avatar"><img src="'+foto+'" alt=""></div>';
    }
    var jins = e.jins || 'erkak';
    return '<div class="eslatma-avatar"><div class="eslatma-avatar-fallback">'+(jins==='ayol'?'👩':'👨')+'</div></div>';
}
function renderEslatmalar(){
    var list = document.getElementById('eslatmalarList');
    var items = CURRENT_ESLATMA_ITEMS.slice();

    if(CURRENT_ESLATMA_FILTER === 'today'){
        items = items.filter(i => i.kq === 0);
    } else if(CURRENT_ESLATMA_FILTER === 'week'){
        items = items.filter(i => i.kq !== null && i.kq <= 7);
    } else if(CURRENT_ESLATMA_FILTER === 'month'){
        items = items.filter(i => i.kq !== null && i.kq <= 30);
    }

    if(!items.length){
        list.innerHTML = '<p style="color:#aaa;text-align:center;padding:20px;">Bu toifada ma\'lumot yo\'q</p>';
        return;
    }

    var html = items.map(function(item){
        var e = item.e, kq = item.kq, sid = e.shaxs_id || e.id;
        var iCls, kCls, kTxt;

        if(kq === 0){
            iCls='eslatma-bugun'; kCls='kun-bugun'; kTxt='🎉 Bugun!';
        } else if(kq !== null && kq <= 7){
            iCls='eslatma-1hafta'; kCls='kun-yaqin'; kTxt=kq+' kun qoldi';
        } else if(kq !== null && kq <= 30){
            iCls='eslatma-1oy'; kCls='kun-orta'; kTxt=kq+' kun qoldi';
        } else {
            iCls='eslatma-keyingi'; kCls='kun-uzoq'; kTxt=(kq !== null)?kq+' kun qoldi':'—';
        }

        var st = '';
        if(item.sana){
            var pp = String(item.sana).split('-');
            st = (pp.length === 3) ? pp[2]+'.'+pp[1]+'.'+pp[0] : item.sana;
        }

        return '<div class="eslatma-item '+iCls+'" onclick="shaxsMalumot('+sid+')">' +
            getBirthdayVisual(e) +
            '<div style="min-width:0;">' +
            '<div class="eslatma-ism">'+_decode((e.ism||'')+' '+(e.familiya||''))+'</div>' +
            '<div class="eslatma-sana">🎂 '+st+'</div>' +
            '</div>' +
            '<span class="eslatma-kun '+kCls+'">'+kTxt+'</span>' +
            '</div>';
    }).join('');

    list.innerHTML = '<div class="eslatma-grid">'+html+'</div>';
}
function eslatmalarYukla(){
    var list = document.getElementById('eslatmalarList');
    list.innerHTML = '<p style="color:#aaa;text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Yuklanmoqda...</p>';

    fetch('api/eslatma.php')
        .then(r=>r.json())
        .then(function(data){
            if(!data.success || !data.data || !data.data.length){
                CURRENT_ESLATMA_ITEMS = [];
                list.innerHTML = '<p style="color:#aaa;text-align:center;padding:20px;">Eslatmalar yo\'q</p>';
                return;
            }

            var bugun = new Date();
            bugun.setHours(0,0,0,0);

            CURRENT_ESLATMA_ITEMS = data.data.map(function(e){
                var sana = e.sana || e.eslatma_sana || e.tugilgan_sana || null;
                var kq = null;
                var yosh = _yosh(e.tugilgan_sana);

                if(sana){
                    var p = String(sana).split('-');
                    var oy = parseInt(p[1],10)-1;
                    var kun = parseInt(p[2],10);
                    var tug = new Date(bugun.getFullYear(), oy, kun);
                    tug.setHours(0,0,0,0);

                    var diff = Math.round((tug - bugun)/86400000);
                    if(diff < 0){
                        tug = new Date(bugun.getFullYear()+1, oy, kun);
                        tug.setHours(0,0,0,0);
                        diff = Math.round((tug - bugun)/86400000);
                    }
                    kq = diff;
                }

                return {e:e, kq:kq, sana:sana, yosh:yosh};
            });

            CURRENT_ESLATMA_ITEMS.sort(function(a,b){
                if ((a.yosh ?? 9999) !== (b.yosh ?? 9999)) return (b.yosh ?? -1) - (a.yosh ?? -1);
                return ((a.kq !== null) ? a.kq : 9999) - ((b.kq !== null) ? b.kq : 9999);
            });

            renderEslatmalar();
        })
        .catch(function(){
            list.innerHTML = '<p style="color:#f45656;text-align:center;padding:16px;">Xatolik yuz berdi</p>';
        });
}

/* =========================
   QIDIRUV
   ========================= */
var _qTimer = null, _qPrev = '';

document.getElementById('qidiruv')?.addEventListener('input', function(){
    var q = this.value.trim();
    if(q === _qPrev) return;
    _qPrev = q;
    clearTimeout(_qTimer);

    if(q.length < 1){
        document.getElementById('qidiruvNatijalar').style.display='none';
        return;
    }
    _qTimer = setTimeout(qidiruvQilish, 300);
});

document.getElementById('qidiruv')?.addEventListener('keydown', function(e){
    if(e.key==='Enter'){
        clearTimeout(_qTimer);
        qidiruvQilish();
    }
    if(e.key==='Escape'){
        document.getElementById('qidiruvNatijalar').style.display='none';
    }
});

document.addEventListener('click', function(e){
    var w = document.querySelector('.search-wrapper');
    if(w && !w.contains(e.target) && document.getElementById('qidiruvNatijalar')){
        document.getElementById('qidiruvNatijalar').style.display='none';
    }
});

function qidiruvQilish(){
    var q = document.getElementById('qidiruv').value.trim();
    var nat = document.getElementById('qidiruvNatijalar');

    if(q.length < 1){
        nat.style.display='none';
        return;
    }

    nat.style.display='block';
    nat.innerHTML='<div style="padding:12px 16px;color:#aaa;font-size:13px;"><i class="fas fa-spinner fa-spin"></i> Qidirilmoqda...</div>';

    fetch('api/shajara.php?qidiruv='+encodeURIComponent(q))
        .then(r=>r.json())
        .then(function(data){
            if(!data.success || !data.data || !data.data.length){
                nat.innerHTML='<div style="padding:14px 16px;color:#aaa;font-size:13px;text-align:center;"><i class="fas fa-search"></i> Natija topilmadi</div>';
                return;
            }

            nat.innerHTML = data.data.slice(0,10).map(function(s){
                var jins=s.jins||'erkak', foto=fotoUrl(s.foto||null);
                var fEl = foto
                    ? '<img src="'+foto+'" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;">'
                    : '<div style="width:36px;height:36px;border-radius:50%;background:'+(jins==='ayol'?'#f06292':'#5c6bc0')+';display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;">'+(jins==='ayol'?'👩':'👨')+'</div>';

                return '<div class="qn-item" onclick="qnTanlash('+s.id+')">' +
                    fEl +
                    '<div><div style="font-weight:600;font-size:13px;">'+_decode((s.ism||'')+' '+(s.familiya||''))+'</div>' +
                    '<div style="font-size:11px;color:#999;">'+_sanaf(s.tugilgan_sana||s.dob)+'</div></div></div>';
            }).join('');
        })
        .catch(function(){
            nat.innerHTML='<div style="padding:10px 14px;color:#f45656;font-size:13px;">Xatolik</div>';
        });
}

function qnTanlash(id){
    document.getElementById('qidiruvNatijalar').style.display='none';
    document.getElementById('qidiruv').value='';
    _qPrev='';
    shajaraYukla(id);
    var sel=document.getElementById('shaxsSelect');
    if(sel) sel.value=id;
}

/* ========================================================
   LIGHTBOX (KATTA RASM KO'RISH) FUNKSIYALARI
   ======================================================== */
function openLightbox(url) {
    var lb = document.getElementById('shajaraLightbox');
    if (!lb) {
        lb = document.createElement('div');
        lb.id = 'shajaraLightbox';
        lb.style.cssText = 'position:fixed; inset:0; background:rgba(10,15,30,0.92); z-index:9999; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 0.25s ease; backdrop-filter:blur(5px);';
        lb.innerHTML = '<img id="lbImg" src="" style="max-width:90vw; max-height:90vh; border-radius:12px; box-shadow:0 20px 50px rgba(0,0,0,0.5); transform:scale(0.95); transition:transform 0.25s ease; border: 3px solid rgba(255,255,255,0.1);">' +
                       '<button onclick="closeLightbox()" style="position:absolute; top:25px; right:35px; background:rgba(255,255,255,0.1); border:none; color:white; width:45px; height:45px; border-radius:50%; font-size:20px; cursor:pointer; transition:background 0.2s;"><i class="fas fa-times"></i></button>';
        document.body.appendChild(lb);
        lb.addEventListener('click', function(e){
            if(e.target === lb) closeLightbox();
        });
        lb.querySelector('button').addEventListener('mouseover', function(){ this.style.background = 'rgba(244,86,86,0.8)'; });
        lb.querySelector('button').addEventListener('mouseout', function(){ this.style.background = 'rgba(255,255,255,0.1)'; });
    }
    document.getElementById('lbImg').src = url;
    lb.style.display = 'flex';
    void lb.offsetWidth; // Reflow
    lb.style.opacity = '1';
    document.getElementById('lbImg').style.transform = 'scale(1)';
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    var lb = document.getElementById('shajaraLightbox');
    if (lb) {
        lb.style.opacity = '0';
        document.getElementById('lbImg').style.transform = 'scale(0.95)';
        setTimeout(function(){
            lb.style.display = 'none';
            if (!document.querySelector('.umodal.active') && !document.querySelector('.shaxsmodal.active')) {
                document.body.style.overflow = '';
            }
        }, 250);
    }
}

/* ========================================================
   SHAXS MODAL, GALEREYA VA TIMELINE (MUKAMMAL DIZAYN)
   ======================================================== */
function shaxsMalumot(id){
    document.getElementById('shaxsModal').setAttribute('data-shaxs-id', id);
    fetch('api/shaxs.php?id=' + id)
        .then(r=>r.json())
        .then(function(data){
            if(!data.success || !data.data) return;

            var s = data.data, jins=s.jins||'erkak', foto=fotoUrl(s.foto||null);
            var currentAge = _yosh(s.tugilgan_sana);
            var deathAge = s.vafot_sana ? _yosh(s.tugilgan_sana, s.vafot_sana) : null;
            var ageDisplay = null;

            if (+s.tirik) ageDisplay = currentAge !== null ? currentAge + ' yosh' : null;
            else if (deathAge !== null && currentAge !== null) ageDisplay = deathAge + ' yosh (' + currentAge + ')';
            else if (deathAge !== null) ageDisplay = deathAge + ' yosh';
            else if (currentAge !== null) ageDisplay = currentAge + ' yosh';

            document.getElementById('shaxsModalTitle').textContent = _decode((s.ism||'')+' '+(s.familiya||''));

            var fHtml = foto
                ? '<img src="'+foto+'" onclick="openLightbox(\''+foto+'\')" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:4px solid #667eea; cursor:pointer; box-shadow:0 8px 20px rgba(102,126,234,0.3); transition:transform 0.2s;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'" onerror="this.style.display=\'none\'">'
                : '<div style="width:100px;height:100px;border-radius:50%;background:'+(jins==='ayol'?'#f06292':'#5c6bc0')+';display:inline-flex;align-items:center;justify-content:center;font-size:42px; box-shadow:0 8px 20px rgba(0,0,0,0.1);">'+(jins==='ayol'?'👩':'👨')+'</div>';

            var rows = [
                ['📅 Tug\'ilgan', _sanaf(s.tugilgan_sana)||'—'],
                ageDisplay ? ['🎂 Yoshi', ageDisplay] : null,
                ['⚤ Jins', jins==='ayol'?'👩 Ayol':'👨 Erkak'],
                ['💚 Holati', +s.tirik ? '✅ Tirik' : '🕊 Vafot etgan'],
                s.vafot_sana ? ['🕯 Vafot sanasi', _sanaf(s.vafot_sana)] : null,
                s.farzandlar_soni ? ['👶 Farzandlar', s.farzandlar_soni+' ta'] : null,
                s.telefon ? ['📞 Telefon', '<a href="tel:'+_escapeHtml(_decode(s.telefon))+'" style="color:#667eea; font-weight:600;">'+_escapeHtml(_decode(s.telefon))+'</a>'] : null,
                s.kasbi ? ['💼 Kasbi', _escapeHtml(_decode(s.kasbi))] : null,
                s.tugilgan_joy ? ['📍 Manzil', _escapeHtml(_decode(s.tugilgan_joy))] : null
            ].filter(Boolean);

            var toHtml = '';
            if(s.turmush_ortogi_id){
                var toIsm = _decode(s.turmush_ortogi_ism || s.turmush_ortogi_ismi || ('ID:'+s.turmush_ortogi_id));
                toHtml = '<tr style="border-bottom:1px solid rgba(120,130,160,.14);"><td style="padding:10px 8px;color:var(--text-muted);width:130px;">💑 Juft</td><td style="padding:10px 8px;font-weight:600;color:#e91e63;cursor:pointer;" onclick="shaxsMalumot('+s.turmush_ortogi_id+')">'+toIsm+'</td></tr>';
            }

            var galleryHtml = '';
            if (s.galereya && Array.isArray(s.galereya) && s.galereya.length > 0) {
                galleryHtml += '<div style="margin-top:25px;">';
                galleryHtml += '<h3 style="font-size:15px; color:var(--text-main); margin-bottom:12px; border-bottom:1px solid rgba(120,130,160,0.15); padding-bottom:8px; display:flex; align-items:center; gap:8px;"><i class="fas fa-images" style="color:#667eea;"></i> Media Galereya ('+s.galereya.length+')</h3>';
                galleryHtml += '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(85px, 1fr)); gap:12px;">';
                
                s.galereya.forEach(function(gItem) {
                    var gUrl = fotoUrl(gItem.fayl || gItem.url || gItem); 
                    galleryHtml += '<div onclick="openLightbox(\''+gUrl+'\')" style="width:100%; aspect-ratio:1; border-radius:12px; overflow:hidden; cursor:pointer; border:1px solid var(--border); box-shadow:0 4px 10px rgba(0,0,0,0.05);">';
                    galleryHtml += '<img src="'+gUrl+'" style="width:100%; height:100%; object-fit:cover; transition:transform 0.3s;" onmouseover="this.style.transform=\'scale(1.15)\'" onmouseout="this.style.transform=\'scale(1)\'">';
                    galleryHtml += '</div>';
                });
                
                galleryHtml += '</div></div>';
            }

            // TIMELINE HTML QURISH
            var timelineHtml = '';
            if (s.timeline && s.timeline.length > 0) {
                timelineHtml += '<div style="margin-top:30px;">';
                timelineHtml += '<h3 style="font-size:15px; color:var(--text-main); margin-bottom:20px; border-bottom:1px solid rgba(120,130,160,0.15); padding-bottom:8px; display:flex; align-items:center; gap:8px;"><i class="fas fa-stream" style="color:#667eea;"></i> Hayot yo\'li (Timeline)</h3>';
                timelineHtml += '<div style="padding-left: 20px;">';
                
                s.timeline.forEach(function(t, idx) {
                    var iconColor = t.color || '#667eea';
                    var sDate = t.sana ? _sanaf(t.sana) : t.yil;
                    var isLast = (idx === s.timeline.length - 1);
                    
                    timelineHtml += '<div style="position: relative; margin-bottom: ' + (isLast ? '0' : '22px') + '; padding-left: 45px;">';
                    
                    if (!isLast) {
                        timelineHtml += '<div style="position: absolute; left: 15px; top: 32px; bottom: -22px; width: 2px; background: var(--border); opacity: 0.8;"></div>';
                    }

                    timelineHtml += '<div style="position: absolute; left: 0; top: 0; width: 32px; height: 32px; border-radius: 50%; background: var(--bg-card); border: 2.5px solid '+iconColor+'; color:'+iconColor+'; display: flex; align-items: center; justify-content: center; font-size: 13px; box-shadow: 0 3px 8px rgba(0,0,0,0.1); z-index: 2;"><i class="fas '+t.icon+'"></i></div>';
                    timelineHtml += '<div style="background: var(--bg-soft); padding: 14px 18px; border-radius: 12px; border: 1px solid var(--border); border-left: 4px solid '+iconColor+'; transition: transform 0.2s;" onmouseover="this.style.transform=\'translateX(4px)\'" onmouseout="this.style.transform=\'translateX(0)\'">';
                    timelineHtml += '<div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">';
                    timelineHtml += '<div style="font-size: 15px; color: var(--text-main); font-weight: 800;">'+_escapeHtml(_decode(t.sarlavha))+'</div>';
                    timelineHtml += '<div style="font-size: 12px; color: #fff; background: '+iconColor+'; padding: 4px 10px; border-radius: 20px; font-weight: 700; box-shadow: 0 2px 6px '+iconColor+'50;">'+sDate+'</div>';
                    timelineHtml += '</div>';
                    
                    if(t.matn) {
                        timelineHtml += '<div style="font-size: 13.5px; color: var(--text-muted); font-weight: 500; margin-top:4px;">'+_escapeHtml(_decode(t.matn))+'</div>';
                    }

                    // ===========================================
                    // XAVFSIZ TAHRIRLASH VA O'CHIRISH TUGMALARI
                    // ===========================================
                    if(t.is_custom && t.voqea_id) {
                        let safeSana = t.sana || '';
                        let jsTitle = _escapeHtml(JSON.stringify(_decode(t.sarlavha || '')));
                        let jsText = _escapeHtml(JSON.stringify(_decode(t.matn || '')));
                        
                        timelineHtml += '<div style="margin-top: 10px; display: flex; gap: 15px; justify-content: flex-end; border-top: 1px dashed var(--border); padding-top: 8px;">';
                        timelineHtml += '<span onclick="event.stopPropagation(); openEventModal(\'edit\', ' + t.voqea_id + ', \'' + safeSana + '\', ' + jsTitle + ', ' + jsText + ')" style="color: #f5b042; cursor: pointer; font-size: 13px; font-weight: 600; display:flex; align-items:center; gap:5px; transition: 0.2s;"><i class="fas fa-edit"></i> Tahrirlash</span>';
                        timelineHtml += '<span onclick="event.stopPropagation(); openEventModal(\'delete\', ' + t.voqea_id + ', \'' + safeSana + '\', ' + jsTitle + ', ' + jsText + ')" style="color: #f45656; cursor: pointer; font-size: 13px; font-weight: 600; display:flex; align-items:center; gap:5px; transition: 0.2s;"><i class="fas fa-trash"></i> O\'chirish</span>';
                        timelineHtml += '</div>';
                    }
                    
                    timelineHtml += '</div></div>';
                });
                
                timelineHtml += '</div></div>';
            }

            document.getElementById('shaxsModalBody').innerHTML =
                '<div style="text-align:center;margin-bottom:20px;">'+fHtml+'</div>' +
                '<table style="width:100%;border-collapse:collapse;font-size:14.5px;color:var(--text-main);">' +
                rows.map(function(r){
                    return '<tr style="border-bottom:1px solid rgba(120,130,160,.14);"><td style="padding:10px 8px;color:var(--text-muted);width:130px;">'+r[0]+'</td><td style="padding:10px 8px;font-weight:500;">'+r[1]+'</td></tr>';
                }).join('') +
                toHtml +
                '</table>' +
                galleryHtml + 
                timelineHtml +
                '<div style="margin-top:30px;text-align:right;"><a href="admin/tahrirlash.php?id='+s.id+'" style="padding:10px 24px;background:linear-gradient(135deg, #667eea, #764ba2);color:#fff;border-radius:8px;text-decoration:none;font-size:14px; font-weight:600; display:inline-block; box-shadow:0 4px 12px rgba(102,126,234,0.3); transition:transform 0.2s;" onmouseover="this.style.transform=\'translateY(-2px)\'" onmouseout="this.style.transform=\'translateY(0)\'"><i class="fas fa-edit"></i> Tahrirlash</a></div>';

            document.getElementById('shaxsModal').classList.add('active');
            document.body.style.overflow='hidden';
        })
        .catch(console.error);
}

function closeShaxsModal(){
    document.getElementById('shaxsModal').classList.remove('active');
    if(!document.querySelector('.umodal.active') && !document.getElementById('shajaraLightbox')) {
        document.body.style.overflow='';
    }
}

function shaxsBackdrop(e){
    if(e.target===document.getElementById('shaxsModal')) closeShaxsModal();
}

/* ==============================================================
   DARAQT MA'LUMOTINI NORMALLASHTIRISH (COLLAPSE MANTIQI BILAN)
   ============================================================== */
function normalize(node){
    if(!node) return null;

    var toRaw = node.turmush_ortogi || node.turmush_ortogi_info || null;
    var toInfo = null;

    if(toRaw && toRaw.id){
        toInfo = {
            id: toRaw.id,
            ism: _decode(toRaw.ism || ''),
            familiya: _decode(toRaw.familiya || ''),
            jins: toRaw.jins || toRaw.gender || 'erkak',
            tugilgan_sana: toRaw.tugilgan_sana || toRaw.dob || null,
            vafot_sana: toRaw.vafot_sana || null,
            tirik: toRaw.tirik !== undefined ? +toRaw.tirik : 1,
            foto: fotoUrl(toRaw.foto || toRaw.photo || null)
        };
    }

    var rawCh = (node.children || node.farzandlar || []).filter(Boolean);

    rawCh.sort(function(a,b){
        var da = a.tugilgan_sana || a.dob || '9999-12-31';
        var db = b.tugilgan_sana || b.dob || '9999-12-31';
        if (da === db) {
            var an = ((a.ism || '') + ' ' + (a.familiya || '')).toLowerCase().trim();
            var bn = ((b.ism || '') + ' ' + (b.familiya || '')).toLowerCase().trim();
            return an.localeCompare(bn, 'uz');
        }
        return da < db ? -1 : 1;
    });

    var processedCh = rawCh.map(function(child, idx){
        var n = normalize(child);
        if(n) n.farzand_tartibi = idx + 1;
        return n;
    }).filter(Boolean);

    var isCollapsed = !!_collapsedMap[node.id];

    return {
        id: node.id,
        ism: _decode(node.ism || ''),
        familiya: _decode(node.familiya || ''),
        jins: node.jins || node.gender || 'erkak',
        tugilgan_sana: node.tugilgan_sana || node.dob || null,
        vafot_sana: node.vafot_sana || null,
        tirik: node.tirik !== undefined ? +node.tirik : 1,
        foto: fotoUrl(node.foto || node.photo || null),
        telefon: node.telefon || '',
        turmush_ortogi_id: node.turmush_ortogi_id || node.spouse_id || null,
        turmush_ortogi_info: toInfo,
        farzand_tartibi: node.farzand_tartibi || null,
        children: isCollapsed ? null : (processedCh.length ? processedCh : null),
        _originalChildrenCount: processedCh.length
    };
}

function buildPCM(rootData){
    var map={}, i=0;
    function walk(n){
        if(!n) return;
        if(n.id && n.id!==0 && !(n.id in map)){
            map[n.id] = i % LINK_COLORS.length;
            i++;
        }
        (n.children||[]).forEach(walk);
    }
    walk(rootData);
    return map;
}

/* =========================
   OILAVIY BLOCK WIDTH HISOBI
   ========================= */
function getSelfLeftExtent(data){
    return NW / 2;
}
function getSelfRightExtent(data){
    return (NW / 2) + ((data && data.turmush_ortogi_info && data.turmush_ortogi_info.id) ? (GAP + SW) : 0);
}
function getSelfVisualWidth(data){
    return getSelfLeftExtent(data) + getSelfRightExtent(data);
}
function getFamilyBlockHeight(node){
    var mainH = node._cardHeight || NH;
    var spouseH = node._spouseHeight || 0;
    return Math.max(mainH, spouseH);
}
function getDynamicSiblingGap(prevChild, nextChild){
    var gap = FAMILY_BASE_GAP;

    var prevHasSpouse = !!(prevChild && prevChild.data && prevChild.data.turmush_ortogi_info && prevChild.data.turmush_ortogi_info.id);
    var nextHasSpouse = !!(nextChild && nextChild.data && nextChild.data.turmush_ortogi_info && nextChild.data.turmush_ortogi_info.id);

    if (prevHasSpouse || nextHasSpouse) {
        gap = Math.max(gap, FAMILY_SPOUSE_GAP);
    }

    var prevBig = prevChild && prevChild._subtreeVisualWidth > 260;
    var nextBig = nextChild && nextChild._subtreeVisualWidth > 260;

    if (prevBig || nextBig) {
        gap = Math.max(gap, FAMILY_BIG_GAP);
    }

    return gap;
}
function computeSubtreeVisualWidth(node){
    if(!node) return 0;

    node._nameLines = getMainNameLines(node.data);
    node._cardHeight = getMainCardHeight(node.data);

    if (node.data && node.data.turmush_ortogi_info && node.data.turmush_ortogi_info.id) {
        node._spouseNameLines = getSpouseNameLines(node.data.turmush_ortogi_info);
        node._spouseHeight = getSpouseCardHeight(node.data.turmush_ortogi_info);
    } else {
        node._spouseNameLines = [];
        node._spouseHeight = 0;
    }

    node._selfLeftExtent = getSelfLeftExtent(node.data);
    node._selfRightExtent = getSelfRightExtent(node.data);
    node._selfVisualWidth = node._selfLeftExtent + node._selfRightExtent;

    if(!node.children || !node.children.length){
        node._subtreeVisualWidth = node._selfVisualWidth;
        return node._subtreeVisualWidth;
    }

    node.children.forEach(function(ch){
        computeSubtreeVisualWidth(ch);
    });

    var totalChildrenWidth = 0;
    for (var i = 0; i < node.children.length; i++) {
        totalChildrenWidth += node.children[i]._subtreeVisualWidth || NW;
        if (i > 0) {
            totalChildrenWidth += getDynamicSiblingGap(node.children[i - 1], node.children[i]);
        }
    }

    node._subtreeVisualWidth = Math.max(node._selfVisualWidth, totalChildrenWidth);
    return node._subtreeVisualWidth;
}

function assignDynamicY(root){
    var nodes = root.descendants();
    var depthHeights = {};

    nodes.forEach(function(n){
        var h = getFamilyBlockHeight(n);
        depthHeights[n.depth] = Math.max(depthHeights[n.depth] || 0, h);
    });

    var depthTop = {};
    var cursorY = 0;
    Object.keys(depthHeights).sort(function(a,b){ return a-b; }).forEach(function(depthStr){
        var d = parseInt(depthStr, 10);
        depthTop[d] = cursorY;
        cursorY += depthHeights[d] + LEVEL_GAP;
    });

    nodes.forEach(function(n){
        n.y = depthTop[n.depth] || 0;
    });
}

function shiftSubtree(node, deltaX){
    if (!node) return;
    node.x += deltaX;
    if (node.children && node.children.length) {
        node.children.forEach(function(ch){
            shiftSubtree(ch, deltaX);
        });
    }
}

function getNodeFamilyLeft(node){
    return node.x - (node._selfLeftExtent || (NW / 2));
}

function getNodeFamilyRight(node){
    return getNodeFamilyLeft(node) + (node._selfVisualWidth || NW);
}

function getCollisionGap(prevNode, nextNode){
    var gap = FAMILY_BASE_GAP;

    var prevHasSpouse = !!(prevNode && prevNode.data && prevNode.data.turmush_ortogi_info && prevNode.data.turmush_ortogi_info.id);
    var nextHasSpouse = !!(nextNode && nextNode.data && nextNode.data.turmush_ortogi_info && nextNode.data.turmush_ortogi_info.id);

    if (prevHasSpouse || nextHasSpouse) gap = Math.max(gap, FAMILY_SPOUSE_GAP);
    if ((prevNode && prevNode._subtreeVisualWidth > 260) || (nextNode && nextNode._subtreeVisualWidth > 260)) {
        gap = Math.max(gap, FAMILY_BIG_GAP);
    }

    return gap;
}

function resolveDepthCollisions(root){
    var nodes = root.descendants();
    var byDepth = {};

    nodes.forEach(function(n){
        if (!byDepth[n.depth]) byDepth[n.depth] = [];
        byDepth[n.depth].push(n);
    });

    Object.keys(byDepth).forEach(function(depthKey){
        var arr = byDepth[depthKey].slice().sort(function(a, b){
            return getNodeFamilyLeft(a) - getNodeFamilyLeft(b);
        });

        var prev = null;

        arr.forEach(function(node){
            if (!prev) {
                prev = node;
                return;
            }

            var prevRight = getNodeFamilyRight(prev);
            var currentLeft = getNodeFamilyLeft(node);
            var neededGap = getCollisionGap(prev, node);
            var minLeft = prevRight + neededGap;

            if (currentLeft < minLeft) {
                var delta = minLeft - currentLeft;
                shiftSubtree(node, delta);
            }

            prev = node;
        });
    });
}

function applyFamilyBlockLayout(root){
    computeSubtreeVisualWidth(root);

    function walk(node, left){
        node._layoutLeft = left;
        node._layoutWidth = node._subtreeVisualWidth;

        var hasSpouse = node.data && node.data.turmush_ortogi_info && node.data.turmush_ortogi_info.id;
        var spouseOffset = hasSpouse ? (GAP + SW) / 2 : 0;

        if(!node.children || !node.children.length) {
            node.x = left + (node._subtreeVisualWidth / 2) - spouseOffset;
            return;
        }

        var totalChildrenWidth = 0;
        for (var i = 0; i < node.children.length; i++) {
            totalChildrenWidth += node.children[i]._subtreeVisualWidth || NW;
            if (i > 0) {
                totalChildrenWidth += getDynamicSiblingGap(node.children[i - 1], node.children[i]);
            }
        }

        var childStart = left + (node._subtreeVisualWidth - totalChildrenWidth) / 2;

        node.children.forEach(function(ch, idx){
            walk(ch, childStart);
            childStart += (ch._subtreeVisualWidth || NW);
            if (idx < node.children.length - 1) {
                childStart += getDynamicSiblingGap(ch, node.children[idx + 1]);
            }
        });

        var firstChild = node.children[0];
        var lastChild = node.children[node.children.length - 1];
        node.x = (firstChild.x + lastChild.x) / 2 - spouseOffset;
    }

    walk(root, 0);
    assignDynamicY(root);
    resolveDepthCollisions(root);

    function recenterParents(n) {
        if (!n.children || !n.children.length) return;
        n.children.forEach(recenterParents);
        
        var hasSpouse = n.data && n.data.turmush_ortogi_info && n.data.turmush_ortogi_info.id;
        var spouseOffset = hasSpouse ? (GAP + SW) / 2 : 0;
        
        var firstChild = n.children[0];
        var lastChild = n.children[n.children.length - 1];
        n.x = (firstChild.x + lastChild.x) / 2 - spouseOffset;
    }
    recenterParents(root);
}

/* =========================
   SHAJARA YUKLASH VA CHIZISH
   ========================= */
function shajaraYukla(id){
    _tanlangan = null;
    _collapsedMap = {}; 
    var div = document.getElementById('shajaraDiagram');
    div.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i>&nbsp;Yuklanmoqda...</div>';

    var url = id ? 'api/shajara.php?id=' + encodeURIComponent(id) : 'api/shajara.php?barcha=1';

    fetch(url)
        .then(function(r){
            if(!r.ok) throw new Error('Server javobi xato: ' + r.status);
            return r.json();
        })
        .then(function(resp){
            if(!resp || !resp.success || !resp.data){
                throw new Error(resp && resp.message ? resp.message : 'API dan to‘g‘ri ma\'lumot kelmadi');
            }

            var daraxt = resp.data.daraxt, spouses = resp.data.spouses || [];
            CURRENT_TREE_RAW = JSON.parse(JSON.stringify(daraxt));
            CURRENT_SPOUSES = spouses;

            if(Array.isArray(daraxt)){
                if(!daraxt.length){
                    div.innerHTML = '<div class="no-data">Shaxslar topilmadi</div>';
                    return;
                }
                if(daraxt.length === 1){
                    daraxt = daraxt[0];
                } else {
                    daraxt = {
                        id:0, ism:'Oila', familiya:'daraxti', jins:'erkak',
                        tugilgan_sana:null, tirik:true, foto:null,
                        turmush_ortogi_id:null, turmush_ortogi:null, children:daraxt
                    };
                }
            }

            div.innerHTML = '';
            chizishD3(daraxt, spouses, false);
        })
        .catch(function(e){
            console.error('Shajara yuklash xatosi:', e);
            div.innerHTML = '<div class="no-data"><i class="fas fa-exclamation-circle"></i>&nbsp;Xatolik: '+e.message+'</div>';
        });
}

function chizishD3(apiData, spouses, isUpdate){
    var container = document.getElementById('shajaraDiagram');
    d3.select('#shajaraDiagram').selectAll('*').remove();

    var W = container.clientWidth || 1000;
    var H = container.clientHeight || 700;

    _svg = d3.select('#shajaraDiagram').append('svg').attr('width', W).attr('height', H);
    var defs = _svg.append('defs');

    // Minimap Container
    var minimapContainer = d3.select('#shajaraDiagram').append('div')
        .attr('class', 'minimap-container no-print')
        .style('position', 'absolute')
        .style('bottom', '20px')
        .style('right', '20px')
        .style('width', '220px')
        .style('height', '160px')
        .style('background', document.body.classList.contains('dark-mode') ? 'rgba(30,41,59,0.95)' : 'rgba(255,255,255,0.95)')
        .style('border', '1px solid ' + (document.body.classList.contains('dark-mode') ? '#334155' : '#e2e8f0'))
        .style('border-radius', '12px')
        .style('box-shadow', '0 4px 15px rgba(0,0,0,0.15)')
        .style('z-index', '100')
        .style('overflow', 'hidden')
        .style('transition', 'background 0.3s, border 0.3s');

    _minimapSvg = minimapContainer.append('svg').attr('width', 220).attr('height', 160);
    var minimapGroup = _minimapSvg.append('g').style('pointer-events', 'none');
    
    _g = _svg.append('g').attr('id', 'main-tree-g');
    minimapGroup.append('use').attr('href', '#main-tree-g');

    _minimapViewRect = _minimapSvg.append('rect')
        .attr('fill', 'rgba(102, 126, 234, 0.25)')
        .attr('stroke', '#667eea')
        .attr('stroke-width', 2)
        .attr('rx', 4)
        .style('cursor', 'move');

    var drag = d3.drag().on('drag', function(ev) {
        var t = d3.zoomTransform(_svg.node());
        var newTx = t.x - (ev.dx / _minimapScale) * t.k;
        var newTy = t.y - (ev.dy / _minimapScale) * t.k;
        _svg.call(_zoom.transform, d3.zoomIdentity.translate(newTx, newTy).scale(t.k));
    });
    _minimapViewRect.call(drag);

    _minimapSvg.on('click', function(ev) {
        if (ev.defaultPrevented) return; 
        var ptr = d3.pointer(ev, _minimapSvg.node());
        var x_g_center = (ptr[0] - _minimapTransform.x) / _minimapScale;
        var y_g_center = (ptr[1] - _minimapTransform.y) / _minimapScale;
        var t = d3.zoomTransform(_svg.node());
        var newTx = W/2 - x_g_center * t.k;
        var newTy = H/2 - y_g_center * t.k;
        _svg.transition().duration(300).call(_zoom.transform, d3.zoomIdentity.translate(newTx, newTy).scale(t.k));
    });

    _zoom = d3.zoom().scaleExtent([0.07,3]).on('zoom', function(ev){
        _g.attr('transform',ev.transform);
        updateMinimapViewport(ev.transform);
    });
    _svg.call(_zoom);
    _svg.on('click',function(){
        tanlashniTozala();
        hideTooltip();
    });

    LINK_COLORS.forEach(function(col, i){
        defs.append('marker')
            .attr('id','arr-'+i)
            .attr('viewBox','0 -5 10 10')
            .attr('refX',9).attr('refY',0)
            .attr('markerWidth',6).attr('markerHeight',6)
            .attr('orient','auto')
            .append('path')
            .attr('d','M0,-4L10,0L0,4Z')
            .attr('fill',col)
            .attr('opacity',0.9);
    });

    defs.append('marker')
        .attr('id','arr-g')
        .attr('viewBox','0 -5 10 10')
        .attr('refX',9).attr('refY',0)
        .attr('markerWidth',6).attr('markerHeight',6)
        .attr('orient','auto')
        .append('path')
        .attr('d','M0,-4L10,0L0,4Z')
        .attr('fill','#90a4ae')
        .attr('opacity',0.7);

    function mkSh(id,dy,blur,col){
        var f=defs.append('filter').attr('id',id).attr('x','-30%').attr('y','-30%').attr('width','160%').attr('height','160%');
        f.append('feDropShadow').attr('dx',0).attr('dy',dy).attr('stdDeviation',blur).attr('flood-color',col);
    }
    mkSh('sh-n',3,6,'rgba(0,0,0,.10)');
    mkSh('sh-s',0,16,'rgba(102,126,234,.6)');
    mkSh('sh-d',1,2,'rgba(0,0,0,.04)');
    mkSh('sh-sp',3,5,'rgba(240,98,146,.22)');

    function mkG(id,c1,c2){
        var g=defs.append('linearGradient').attr('id',id).attr('x1','0%').attr('y1','0%').attr('x2','100%').attr('y2','100%');
        g.append('stop').attr('offset','0%').attr('stop-color',c1);
        g.append('stop').attr('offset','100%').attr('stop-color',c2);
    }
    mkG('gr-e','#f0f3ff','#e4eaf8');
    mkG('gr-a','#fff0f5','#fde0ec');
    mkG('gr-v','#f6f6f6','#e8e8e8');
    mkG('gr-es','#dde3f8','#b8c4f0');
    mkG('gr-as','#fde0ec','#f9b8d4');
    mkG('gr-sp','#fff5f8','#ffe8f2');
    mkG('tp-e','#7986cb','#5c6bc0');
    mkG('tp-a','#f06292','#e91e63');
    mkG('tp-v','#b0bec5','#90a4ae');
    mkG('tp-sp','#f48fb1','#f06292');

    _allSpouses = [];
    _nodeMap = {};

    var normalized = normalize(apiData);
    var root = d3.hierarchy(normalized);
    var pcm = buildPCM(normalized);

    applyFamilyBlockLayout(root);

    var nodes = root.descendants();
    var links = root.links();

    nodes.forEach(function(n){
        if(n.data && n.data.id) _nodeMap[n.data.id] = n;
    });

    var xs = nodes.map(function(d){ return d.x; });
    var offX = W/2 - (d3.min(xs) + d3.max(xs))/2;

    var genMap = {};
    nodes.forEach(function(n){
        if(typeof genMap[n.depth] === 'undefined') genMap[n.depth] = n.y;
    });

    var genLayer = _g.append('g').attr('class','generation-labels');
    Object.keys(genMap).forEach(function(depthStr){
        var depth = parseInt(depthStr,10);
        var y = genMap[depth];
        var gx = d3.min(xs) + offX - NW/2 - 180;

        var group = genLayer.append('g').attr('transform','translate('+gx+','+(y+2)+')');
        group.append('rect')
            .attr('width',120).attr('height',42)
            .attr('rx',21).attr('ry',21)
            .attr('fill','var(--bg-card)')
            .attr('stroke','#cfd8f3')
            .attr('stroke-width',1.5)
            .attr('opacity',0.98);

        group.append('text')
            .attr('x',60).attr('y',27)
            .attr('text-anchor','middle')
            .style('font-size','17px')
            .style('font-weight','900')
            .style('fill','#5b6fc9')
            .text((depth+1)+'-avlod');
    });

    _allLinks = _g.selectAll('.lnk')
        .data(links)
        .enter()
        .append('path')
        .attr('class','lnk')
        .attr('fill','none')
        .attr('stroke',function(d){
            if(!d.source.data.tirik || !d.target.data.tirik) return '#90a4ae';
            var ci = pcm[d.source.data.id];
            return ci !== undefined ? LINK_COLORS[ci] : '#c5cae9';
        })
        .attr('stroke-width',2.2)
        .attr('stroke-linecap','round')
        .attr('stroke-dasharray',function(d){
            return (!d.source.data.tirik || !d.target.data.tirik) ? '7,4' : '0';
        })
        .attr('opacity',0.78)
        .attr('marker-end',function(d){
            if(!d.source.data.tirik || !d.target.data.tirik) return 'url(#arr-g)';
            var ci = pcm[d.source.data.id];
            return ci !== undefined ? 'url(#arr-'+ci+')' : 'url(#arr-0)';
        })
        .attr('d',function(d){
            var sx = d.source.x + offX,
                sy = d.source.y + (d.source._cardHeight || NH),
                tx = d.target.x + offX,
                ty = d.target.y - 2,
                my = (sy + ty) / 2;
            return 'M'+sx+','+sy+' C'+sx+','+my+' '+tx+','+my+' '+tx+','+ty;
        });

    _allNodes = _g.selectAll('.nd')
        .data(nodes)
        .enter()
        .append('g')
        .attr('class','nd')
        .attr('transform',function(d){
            return 'translate('+(d.x+offX-NW/2)+','+d.y+')';
        })
        .style('cursor','pointer')
        .on('click',function(ev,d){
            ev.stopPropagation();
            nodeClick(d);
        })
        .on('mouseenter',function(ev,d){ showTooltip(ev,d.data); })
        .on('mousemove',function(ev){ moveTooltip(ev); })
        .on('mouseleave',function(){ hideTooltip(); });

    _allNodes.append('rect')
        .attr('class','karta')
        .attr('width',NW).attr('height',function(d){ return d._cardHeight || NH; })
        .attr('rx',13).attr('ry',13)
        .attr('fill',function(d){
            if(d.data.id===0) return 'var(--bg-card)';
            if(!d.data.tirik) return 'url(#gr-v)';
            return d.data.jins==='ayol' ? 'url(#gr-a)' : 'url(#gr-e)';
        })
        .attr('stroke',function(d){
            if(d.data.id===0) return '#e0e0e0';
            if(!d.data.tirik) return '#bbb';
            return d.data.jins==='ayol' ? '#f48fb1' : '#7986cb';
        })
        .attr('stroke-width',function(d){ return d.depth===0?2:1; })
        .attr('filter','url(#sh-n)');

    _allNodes.filter(function(d){ return d.data.id!==0; })
        .append('rect')
        .attr('width',NW).attr('height',7)
        .attr('rx',13).attr('ry',13)
        .attr('fill',function(d){
            if(!d.data.tirik) return 'url(#tp-v)';
            return d.data.jins==='ayol' ? 'url(#tp-a)' : 'url(#tp-e)';
        });

    _allNodes.filter(function(d){ return d.data.id===0; })
        .append('text')
        .attr('x',NW/2).attr('y',NH/2)
        .attr('text-anchor','middle')
        .attr('dominant-baseline','middle')
        .style('font-size','10px')
        .style('fill','#ccc')
        .text('Oila daraxti');

    var realNodes = _allNodes.filter(function(d){ return d.data.id!==0; });

    realNodes.filter(function(d){ return d.depth===0; })
        .append('text')
        .attr('x',NW-8).attr('y',18)
        .attr('text-anchor','middle')
        .style('font-size','10px')
        .text('⭐');

    var FCX=NW/2, FCY=FR+18;

    realNodes.append('circle')
        .attr('cx',FCX).attr('cy',FCY)
        .attr('r',FR+3)
        .attr('fill','rgba(255,255,255,.7)');

    realNodes.append('circle')
        .attr('class','foto-doira')
        .attr('cx',FCX).attr('cy',FCY)
        .attr('r',FR)
        .attr('fill',function(d){ return d.data.jins==='ayol'?'#f06292':'#5c6bc0'; })
        .attr('stroke','#fff')
        .attr('stroke-width',2);

    realNodes.each(function(d){
        var el=d3.select(this),
            dia=(FR-1)*2,
            x0=FCX-(FR-1),
            y0=FCY-(FR-1);

        var cid='clip-n-'+d.data.id+'-'+Math.random().toString(36).slice(2,6);
        defs.append('clipPath').attr('id',cid).append('circle').attr('cx',FCX).attr('cy',FCY).attr('r',FR-1);

        if(d.data.foto){
            el.append('image')
                .attr('href',d.data.foto)
                .attr('x',x0).attr('y',y0)
                .attr('width',dia).attr('height',dia)
                .attr('clip-path','url(#'+cid+')')
                .attr('preserveAspectRatio','xMidYMid slice')
                .on('error',function(){
                    d3.select(this).remove();
                    el.append('text')
                        .attr('x',FCX).attr('y',FCY+9)
                        .attr('text-anchor','middle')
                        .style('font-size','22px')
                        .text(d.data.jins==='ayol'?'👩':'👨');
                });
        }else{
            el.append('text')
                .attr('x',FCX).attr('y',FCY+9)
                .attr('text-anchor','middle')
                .style('font-size','22px')
                .text(d.data.jins==='ayol'?'👩':'👨');
        }
    });

    realNodes.filter(function(d){ return !d.data.tirik; })
        .append('text')
        .attr('x',FCX+FR+1).attr('y',FCY-FR+11)
        .attr('text-anchor','middle')
        .style('font-size','9px')
        .text('🕊');

    realNodes.each(function(d){
        var el = d3.select(this);
        var lines = d._nameLines || getMainNameLines(d.data);

        appendMultiLineText(el, {
            x: NW/2,
            y: FCY + FR + 17,
            lines: lines,
            lineHeight: 12,
            fontSize: '11px',
            fill: '#1a237e',
            weight: '800'
        });

        var extraShift = Math.max(0, (lines.length - 1) * 12);

        el.append('text')
            .attr('x',NW/2).attr('y',FCY + FR + 52 + extraShift)
            .attr('text-anchor','middle')
            .style('font-size','10px')
            .style('font-weight','700')
            .style('fill','#5c6bc0')
            .text(_ageLabel(d.data));

        el.append('text')
            .attr('x',NW/2).attr('y',FCY + FR + 66 + extraShift)
            .attr('text-anchor','middle')
            .style('font-size','10px')
            .style('fill','#7986cb')
            .text(_sanaf(d.data.tugilgan_sana));
    });

    var expandGroup = realNodes.filter(function(d){ return d.data._originalChildrenCount > 0; })
        .append('g')
        .attr('class', 'collapse-btn')
        .style('cursor', 'pointer')
        .on('click', function(ev, d) {
            ev.stopPropagation(); 
            _collapsedMap[d.data.id] = !_collapsedMap[d.data.id]; 
            
            var oldTransform = d3.zoomTransform(_svg.node());
            chizishD3(CURRENT_TREE_RAW, CURRENT_SPOUSES, true);
            _svg.call(_zoom.transform, oldTransform);
        });

    expandGroup.append('circle')
        .attr('cx', NW/2)
        .attr('cy', function(d){ return d._cardHeight || NH; }) 
        .attr('r', 11)
        .attr('fill', function(d) { return _collapsedMap[d.data.id] ? '#48c78e' : '#5c6bc0'; }) 
        .attr('stroke', '#fff')
        .attr('stroke-width', 2)
        .attr('filter', 'url(#sh-n)');

    expandGroup.append('text')
        .attr('x', NW/2)
        .attr('y', function(d){ return (d._cardHeight || NH) + 4; }) 
        .attr('text-anchor', 'middle')
        .style('font-size', '11px')
        .style('fill', '#fff')
        .style('font-weight', 'bold')
        .text(function(d){ 
            return _collapsedMap[d.data.id] ? '+' + d.data._originalChildrenCount : '−'; 
        });

    expandGroup.append('title')
        .text(function(d){ 
            return _collapsedMap[d.data.id] ? "Yoyish (" + d.data._originalChildrenCount + " ta farzand yashiringan)" : "Yig'ish"; 
        });

    realNodes.filter(function(d){ return d.data.farzand_tartibi; })
        .append('circle')
        .attr('class','child-order-badge')
        .attr('cx',12).attr('cy',18)
        .attr('r',10).attr('fill','#ff9800')
        .attr('stroke','#ffffff').attr('stroke-width',2);

    realNodes.filter(function(d){ return d.data.farzand_tartibi; })
        .append('text')
        .attr('x',12).attr('y',21)
        .attr('text-anchor','middle')
        .style('font-size','8.5px')
        .style('fill','#ffffff')
        .style('font-weight','900')
        .text(function(d){ return d.data.farzand_tartibi; });

    nodes.forEach(function(d){
        var ti = d.data.turmush_ortogi_info;
        if(!ti || !ti.id) return;

        var mx = d.x + offX + NW/2;
        var sy = d.y + ((d._cardHeight || NH)-SH)/2;
        var candidateX = mx + GAP;

        var line = _g.append('line').attr('class','spouse-link')
            .attr('x1',mx).attr('y1',d.y + ((d._cardHeight || NH) / 2))
            .attr('x2',candidateX).attr('y2',sy + (SH / 2))
            .attr('stroke','#f8a5c2').attr('stroke-width',1.5)
            .attr('stroke-dasharray','4,3').attr('opacity',0.8);

        var heart = _g.append('text').attr('class','spouse-heart')
            .attr('x',(mx+candidateX)/2).attr('y',d.y + ((d._cardHeight || NH) / 2) + 5)
            .attr('text-anchor','middle').style('font-size','10px').text('💕');

        var sg = _g.append('g').attr('class','spouse-card')
            .attr('transform','translate('+candidateX+','+sy+')')
            .style('cursor','pointer')
            .on('click',function(ev){ ev.stopPropagation(); shaxsMalumot(ti.id); })
            .on('mouseenter',function(ev){ showTooltip(ev,ti); })
            .on('mousemove',function(ev){ moveTooltip(ev); })
            .on('mouseleave',function(){ hideTooltip(); });

        sg.append('rect').attr('width',SW).attr('height',SH)
          .attr('rx',11).attr('ry',11).attr('fill','url(#gr-sp)')
          .attr('stroke','#f48fb1').attr('stroke-width',1).attr('filter','url(#sh-sp)');
          
        sg.append('rect').attr('width',SW).attr('height',5)
          .attr('rx',11).attr('ry',11).attr('fill','url(#tp-sp)');

        var sfx=SW/2, sfy=SFR+12;
        sg.append('circle').attr('cx',sfx).attr('cy',sfy).attr('r',SFR+2).attr('fill','rgba(255,255,255,.75)');
        sg.append('circle').attr('cx',sfx).attr('cy',sfy).attr('r',SFR)
          .attr('fill',ti.jins==='ayol'?'#f06292':'#5c6bc0')
          .attr('stroke','#fff').attr('stroke-width',2);

        var sc2='clip-sp-'+ti.id+'-'+Math.random().toString(36).slice(2,6);
        defs.append('clipPath').attr('id',sc2).append('circle').attr('cx',sfx).attr('cy',sfy).attr('r',SFR-1);

        if(ti.foto){
            sg.append('image').attr('href',ti.foto).attr('x',sfx-(SFR-1)).attr('y',sfy-(SFR-1))
              .attr('width',(SFR-1)*2).attr('height',(SFR-1)*2)
              .attr('clip-path','url(#'+sc2+')').attr('preserveAspectRatio','xMidYMid slice')
              .on('error',function(){
                    d3.select(this).remove();
                    sg.append('text').attr('x',sfx).attr('y',sfy+7)
                      .attr('text-anchor','middle').style('font-size','17px').text(ti.jins==='ayol'?'👩':'👨');
              });
        }else{
            sg.append('text').attr('x',sfx).attr('y',sfy+7)
              .attr('text-anchor','middle').style('font-size','17px').text(ti.jins==='ayol'?'👩':'👨');
        }

        var spouseLines = d._spouseNameLines || getSpouseNameLines(ti);

        appendMultiLineText(sg, {
            x: SW/2,
            y: sfy + SFR + 16,
            lines: spouseLines,
            lineHeight: 11,
            fontSize: '10px',
            fill: '#880e4f',
            weight: '700'
        });

        var spouseShift = Math.max(0, (spouseLines.length - 1) * 11);

        sg.append('text').attr('x',SW/2).attr('y',sfy+SFR+50+spouseShift)
          .attr('text-anchor','middle').style('font-size','10px').style('font-weight','700').style('fill','#e91e63').text(_ageLabel(ti));

        sg.append('text').attr('x',SW/2).attr('y',sfy+SFR+64+spouseShift)
          .attr('text-anchor','middle').style('font-size','10px').style('fill','#f8a5c2').text(_sanaf(ti.tugilgan_sana));

        _allSpouses.push({card:sg,line:line,heart:heart,data:ti,ownerId:d.data.id});
    });

    if (!isUpdate) {
        setTimeout(function(){
            resetZoom(600); 
            setTimeout(initMinimap, 10);
        }, 150);
    } else {
        initMinimap();
    }

    applyVafotFilter();
}

function updateMinimapViewport(t) {
    if(!_minimapViewRect) return;
    var W = document.getElementById('shajaraDiagram').clientWidth || 1000;
    var H = document.getElementById('shajaraDiagram').clientHeight || 700;
    
    var x_g = -t.x / t.k;
    var y_g = -t.y / t.k;
    var w_g = W / t.k;
    var h_g = H / t.k;

    var x_m = _minimapTransform.x + x_g * _minimapScale;
    var y_m = _minimapTransform.y + y_g * _minimapScale;
    var w_m = w_g * _minimapScale;
    var h_m = h_g * _minimapScale;

    _minimapViewRect
        .attr('x', x_m)
        .attr('y', y_m)
        .attr('width', Math.max(1, w_m))
        .attr('height', Math.max(1, h_m));
}

function initMinimap() {
    if(!_g || !_minimapSvg) return;
    
    var bbox = _g.node().getBBox();
    if(!bbox.width || !bbox.height) return;

    var mw = 220, mh = 160, pad = 10;
    var scX = (mw - pad * 2) / bbox.width;
    var scY = (mh - pad * 2) / bbox.height;
    
    _minimapScale = Math.min(scX, scY);
    _minimapTransform.x = (mw - bbox.width * _minimapScale) / 2 - bbox.x * _minimapScale;
    _minimapTransform.y = (mh - bbox.height * _minimapScale) / 2 - bbox.y * _minimapScale;

    _minimapSvg.select('g')
        .attr('transform', 'translate(' + _minimapTransform.x + ',' + _minimapTransform.y + ') scale(' + _minimapScale + ')');

    updateMinimapViewport(d3.zoomTransform(_svg.node()));
}

function nodeClick(d){
    if(!d || d.data.id===0) return;
    _tanlangan = d.data.id;

    _allNodes.each(function(nd){
        var el = d3.select(this);
        var self = nd.data.id === _tanlangan;
        var parentNode = d.parent && d.parent.data.id === nd.data.id;
        var childNode = nd.parent && nd.parent.data.id === _tanlangan;
        var spouseRel = nd.data.turmush_ortogi_id === _tanlangan || d.data.turmush_ortogi_id === nd.data.id;

        if(self){
            el.transition().duration(220).style('opacity',1);
            el.select('.karta').transition().duration(220)
                .attr('fill', nd.data.jins==='ayol'?'url(#gr-as)':'url(#gr-es)')
                .attr('stroke','#667eea')
                .attr('stroke-width',4)
                .attr('filter','url(#sh-s)');
        } else if(parentNode || childNode || spouseRel){
            var op = (_vafotFilter && nd.data.id!==0 && !nd.data.tirik)?0.34:0.85;
            el.transition().duration(220).style('opacity',op);
            el.select('.karta').transition().duration(220)
                .attr('stroke','#9fa8da')
                .attr('stroke-width',2.5)
                .attr('filter','url(#sh-n)');
        } else {
            var op2 = (_vafotFilter && nd.data.id!==0 && !nd.data.tirik)?0.10:0.15;
            el.transition().duration(220).style('opacity',op2);
            el.select('.karta').transition().duration(220).attr('filter','url(#sh-d)');
        }
    });

    _allLinks.transition().duration(220)
        .style('opacity',function(l){
            if(l.source.data.id===_tanlangan || l.target.data.id===_tanlangan) return 1;
            if(_vafotFilter && (!l.source.data.tirik || !l.target.data.tirik)) return 0.05;
            return 0.07;
        })
        .attr('stroke-width',function(l){
            return (l.source.data.id===_tanlangan || l.target.data.id===_tanlangan)?4:2;
        });

    if(_allSpouses && _allSpouses.length){
        _allSpouses.forEach(function(sp){
            var related = sp.ownerId===_tanlangan || sp.data.id===_tanlangan;
            var opacity = related ? 1 : ((_vafotFilter && !sp.data.tirik)?0.10:0.18);
            sp.card.transition().duration(220).style('opacity',opacity);
            sp.line.transition().duration(220).style('opacity',opacity);
            sp.heart.transition().duration(220).style('opacity',opacity);
        });
    }

    var sel = document.getElementById('shaxsSelect');
    if(sel) sel.value = _tanlangan;
    shaxsMalumot(d.data.id);
}

function tanlashniTozala(){
    _tanlangan = null;
    if(!_allNodes) return;

    _allNodes.each(function(d){
        var el = d3.select(this);
        var opacity = (_vafotFilter && d.data.id!==0 && !d.data.tirik)?0.34:1;
        el.transition().duration(220).style('opacity',opacity);

        el.select('.karta').transition().duration(220)
            .attr('fill',function(){
                if(d.data.id===0) return 'var(--bg-card)';
                if(!d.data.tirik) return 'url(#gr-v)';
                return d.data.jins==='ayol'?'url(#gr-a)':'url(#gr-e)';
            })
            .attr('stroke',function(){
                if(d.data.id===0) return '#ccc';
                if(d.depth===0) return '#5c6bc0';
                if(!d.data.tirik) return '#90a4ae';
                return d.data.jins==='ayol'?'#ec407a':'#5c6bc0';
            })
            .attr('stroke-width',function(){ return d.depth===0?3:1.5; })
            .attr('filter','url(#sh-n)');
    });

    if(_allLinks){
        _allLinks.transition().duration(220)
            .style('opacity',function(l){
                if(_vafotFilter && (!l.source.data.tirik || !l.target.data.tirik)) return 0.22;
                return 0.78;
            })
            .attr('stroke-width',2.2);
    }

    if(_allSpouses && _allSpouses.length){
        _allSpouses.forEach(function(sp){
            var opacity = (_vafotFilter && !sp.data.tirik)?0.34:1;
            sp.card.transition().duration(220).style('opacity',opacity);
            sp.line.transition().duration(220).style('opacity',opacity);
            sp.heart.transition().duration(220).style('opacity',opacity);
        });
    }
}

function toggleVafotFilter(){
    _vafotFilter = !_vafotFilter;
    var btn = document.getElementById('vafotFilterBtn');
    if(_vafotFilter) btn.classList.add('active'); else btn.classList.remove('active');
    applyVafotFilter();
}
function applyVafotFilter(){
    if(_tanlangan && _nodeMap[_tanlangan]) nodeClick(_nodeMap[_tanlangan]);
    else tanlashniTozala();
}
function zoomIn(){ if(_svg) _svg.transition().duration(300).call(_zoom.scaleBy,1.3); }
function zoomOut(){ if(_svg) _svg.transition().duration(300).call(_zoom.scaleBy,.77); }

function resetZoom(duration){
    var dur = typeof duration === 'number' ? duration : 500;
    if(!_svg || !_g) return;

    var diagram = document.getElementById('shajaraDiagram');
    var rect = diagram.getBoundingClientRect();
    var W = rect.width || diagram.clientWidth || 1000;
    var H = rect.height || diagram.clientHeight || 700;

    try {
        var bbox = _g.node().getBBox();
        if(!bbox.width || !bbox.height) return;

        var sc = Math.min(W / (bbox.width + 100), H / (bbox.height + 100), 0.95);
        
        var tx = (W - bbox.width * sc) / 2 - bbox.x * sc;
        var ty = (H - bbox.height * sc) / 2 - bbox.y * sc;

        tx += (70 * sc); 

        _svg.transition().duration(dur).call(_zoom.transform, d3.zoomIdentity.translate(tx, ty).scale(sc));
    } catch(e) {
        console.warn('Markazlashtirish xatosi:', e);
    }
}

/* =========================
   TOOLTIP
   ========================= */
function buildTooltipVisual(data){
    if(data.foto){
        return '<div class="tt-avatar"><img src="'+data.foto+'" alt=""></div>';
    }
    return '<div class="tt-avatar"><div class="tt-avatar-fallback">'+(data.jins==='ayol'?'👩':'👨')+'</div></div>';
}
function showTooltip(ev,data){
    if(!data || data.id===0) return;
    var tt = document.getElementById('nodeTooltip');
    
    var currentAge = _yosh(data.tugilgan_sana);
    var deathAge = data.vafot_sana ? _yosh(data.tugilgan_sana, data.vafot_sana) : null;
    var ageDisplay = currentAge !== null ? currentAge + ' yosh' : '—';
    if(!+data.tirik && deathAge !== null && currentAge !== null){
        ageDisplay = deathAge + ' yosh (' + currentAge + ')';
    } else if(!+data.tirik && deathAge !== null){
        ageDisplay = deathAge + ' yosh';
    }

    var ttFullName = ((data.ism||'') + ' ' + (data.familiya||'')).trim();

    var html='';
    html += '<div class="tt-top">';
    html += buildTooltipVisual(data);
    html += '<div class="tt-head">';
    html += '<div class="tt-name">'+_escapeHtml(_decode(ttFullName))+'</div>';
    html += '<div class="tt-sub"><i class="fas fa-user"></i> '+(data.jins==='ayol'?'Ayol':'Erkak')+'</div>';
    html += '</div></div>';

    html += '<div class="tt-divider"></div>';

    html += '<div class="tt-grid">';
    html += '<div class="tt-card"><div class="tt-card-label">Tug‘ilgan sana</div><div class="tt-card-value">'+(_sanaf(data.tugilgan_sana)||'—')+'</div></div>';
    html += '<div class="tt-card"><div class="tt-card-label">Yoshi</div><div class="tt-card-value">'+ageDisplay+'</div></div>';
    html += '<div class="tt-card"><div class="tt-card-label">Telefon</div><div class="tt-card-value">'+(data.telefon?_escapeHtml(_decode(data.telefon)):'—')+'</div></div>';
    html += '<div class="tt-card"><div class="tt-card-label">Holati</div><div class="tt-card-value">'+(+data.tirik?'Tirik':'Vafot etgan')+'</div></div>';
    html += '</div>';

    html += '<div class="tt-badges">';
    html += +data.tirik
        ? '<span class="tt-badge tt-live">Tirik</span>'
        : '<span class="tt-badge tt-dead">Vafot etgan</span>';
    if (data.turmush_ortogi_id) html += '<span class="tt-badge" style="background:#fff0f8;color:#d94f96;">Oilali</span>';
    html += '</div>';

    tt.innerHTML = html;
    tt.classList.add('show');
    moveTooltip(ev);
}
function moveTooltip(ev){
    var tt = document.getElementById('nodeTooltip');
    if(!tt || !tt.classList.contains('show')) return;
    
    var offset = 18, x = ev.clientX + offset, y = ev.clientY + offset;
    tt.style.left = '0px'; 
    tt.style.top = '0px';
    
    var rect = tt.getBoundingClientRect();
    if(x + rect.width > window.innerWidth - 10) x = ev.clientX - rect.width - offset;
    if(y + rect.height > window.innerHeight - 10) y = ev.clientY - rect.height - offset;
    if(x < 10) x = 10;
    if(y < 10) y = 10;
    
    tt.style.left = x + 'px'; 
    tt.style.top = y + 'px';
}
function hideTooltip(){
    var tt = document.getElementById('nodeTooltip');
    if(tt) tt.classList.remove('show');
}

/* INIT */
window.addEventListener('load', function(){
    shajaraYukla('');
});