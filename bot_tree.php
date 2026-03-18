<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Shajara Daraxti</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <style>
        body { margin: 0; padding: 0; background-color: var(--tg-theme-bg-color, #f4f7f6); font-family: 'Segoe UI', Tahoma, sans-serif; overflow: hidden; }
        #tree-container { width: 100vw; height: 100vh; display: block; }
        
        .link { fill: none; stroke: #b0bec5; stroke-width: 2px; }
        .loading-box { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: var(--tg-theme-text-color, #333);}
        
        .card-wrapper { position: relative; width: 110px; margin: 0 auto; cursor: pointer; }

        .order-badge {
            position: absolute; top: -6px; right: -6px; background: #ff5252; color: white;
            font-size: 10px; font-weight: bold; width: 18px; height: 18px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.3); z-index: 10;
        }

        .shajara-card {
            width: 100%; height: 100%; box-sizing: border-box; background: #fff; border-radius: 10px;
            border: 2px solid; box-shadow: 0 3px 6px rgba(0,0,0,0.15); text-align: center;
            overflow: hidden; display: flex; flex-direction: column; color: #333; transition: transform 0.2s;
        }
        .shajara-card:active { transform: scale(0.95); }
        .shajara-card.erkak { border-color: #42a5f5; }
        .shajara-card.ayol { border-color: #ec407a; }
        
        .avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2); margin: 0 auto 4px auto; background: #e0e0e0; pointer-events: none;}
        .avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2); margin: 0 auto 4px auto; background: #cfd8dc; font-size: 22px; line-height: 40px; text-align: center; pointer-events: none;}
        
        .person-main { flex: 1; padding: 6px 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; box-sizing: border-box; cursor: pointer;}
        .shajara-card.erkak .person-main { background: #e3f2fd; }
        .shajara-card.ayol .person-main { background: #fce4ec; }
        
        .person-name { font-weight: bold; font-size: 11px; line-height: 1.2; word-wrap: break-word; pointer-events: none;}
        .person-name div { display: block; pointer-events: none;}
        .person-age { font-size: 10px; color: #555; margin-top: 3px; pointer-events: none;}
        
        .person-spouse { flex: 1; padding: 6px 4px; background: #fff; border-top: 1px dashed #ccc; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; box-sizing: border-box; cursor: pointer;}
        .spouse-icon { position: absolute; top: -9px; left: 5px; font-size: 13px; background: #fff; border-radius: 50%; padding: 1px; pointer-events: none;}
        .spouse-name { font-weight: bold; color: #d81b60; font-size: 11px; line-height: 1.2; word-wrap: break-word; pointer-events: none;}
        .spouse-name div { display: block; pointer-events: none;}

        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.6); z-index: 1000;
            display: none; justify-content: center; align-items: center;
            opacity: 0; transition: opacity 0.3s;
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        
        .profile-modal {
            background: var(--tg-theme-bg-color, #fff); color: var(--tg-theme-text-color, #333);
            width: 85%; max-width: 320px; border-radius: 16px; padding: 24px 20px;
            text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            position: relative; transform: scale(0.9); transition: transform 0.3s;
        }
        .modal-overlay.active .profile-modal { transform: scale(1); }
        
        .close-btn { position: absolute; top: 12px; right: 18px; font-size: 28px; color: #888; cursor: pointer; line-height: 1;}
        
        .prof-img { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 4px solid #42a5f5; margin: 0 auto 12px; background: #eee; font-size: 45px; line-height: 90px; display: block; }
        .prof-name { font-size: 20px; font-weight: bold; margin-bottom: 4px; }
        .prof-dates { font-size: 13px; color: #777; margin-bottom: 20px; }
        
        .prof-detail { display: flex; align-items: center; padding: 12px; background: var(--tg-theme-secondary-bg-color, #f4f7f6); border-radius: 10px; margin-bottom: 10px; font-size: 14px; text-align: left;}
        .prof-detail i { font-size: 18px; width: 30px; text-align: center; margin-right: 10px;}
        .prof-link { color: #42a5f5; text-decoration: none; font-weight: bold; font-size: 15px;}

        .export-btn {
            position: fixed; bottom: 20px; right: 20px;
            background: #42a5f5; color: #fff; padding: 12px 20px;
            border-radius: 50px; font-size: 14px; font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3); cursor: pointer;
            z-index: 900; display: flex; align-items: center; gap: 8px;
            transition: background 0.2s;
        }
        .export-btn:active { background: #1e88e5; transform: scale(0.95); }
    </style>
</head>
<body>

    <div id="loading" class="loading-box"><h2>🌳 Daraxt o'qilmoqda...</h2></div>
    
    <div class="export-btn" id="exportBtn" onclick="exportTree()">📥 Rasmga saqlash</div>

    <div id="tree-container"></div>

    <div id="profileModalOverlay" class="modal-overlay" onclick="closeProfile()">
        <div class="profile-modal" onclick="event.stopPropagation()">
            <div class="close-btn" onclick="closeProfile()">&times;</div>
            <div id="mPhoto" class="prof-img"></div>
            <div id="mName" class="prof-name"></div>
            <div id="mDates" class="prof-dates"></div>
            <div class="prof-detail">
                <i>💼</i> 
                <div>
                    <div style="font-size:11px; color:#888;">Kasbi / Mutaxassisligi</div>
                    <span id="mProf" style="font-weight:600;"></span>
                </div>
            </div>
            <div class="prof-detail" id="phoneContainer">
                <i>📞</i> 
                <div>
                    <div style="font-size:11px; color:#888;">Telefon raqami</div>
                    <span id="mPhone"></span>
                </div>
            </div>
        </div>
    </div>

    <div id="exportModalOverlay" class="modal-overlay" onclick="closeExport()">
        <div class="profile-modal" style="max-width: 90%; width: 350px; padding: 15px;" onclick="event.stopPropagation()">
            <div class="close-btn" onclick="closeExport()">&times;</div>
            <h3 style="margin-top:0; font-size:18px; color: #42a5f5;">✅ Rasm tayyor!</h3>
            <p style="font-size:13px; color:#555; margin-bottom:15px; background: #fff3e0; padding: 10px; border-radius: 8px;">
                📲 Telefoningizga saqlash uchun quyidagi <b>rasm ustiga uzoq bosib turing</b> va <i>"Saqlash" (Save image)</i> tugmasini tanlang.
            </p>
            <div style="max-height: 55vh; overflow: auto; border: 2px solid #eee; border-radius: 8px; background: #f4f7f6;">
                <img id="exportedImage" style="width: 100%; display: block;">
            </div>
        </div>
    </div>

    <script>
        try { window.Telegram.WebApp.expand(); window.Telegram.WebApp.ready(); } catch(e) {}

        window.personDB = {};

        window.onload = function() {
            fetch('api/tree_data.php', { headers: { "ngrok-skip-browser-warning": "true" } })
            .then(res => res.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                if (data) {
                    try {
                        drawTree(data);
                    } catch (err) {
                        alert("Xatolik: Daraxtni chizishda muammo chiqdi.");
                        console.error(err);
                    }
                }
            })
            .catch(err => { 
                document.getElementById('loading').innerHTML = "<h3 style='color:red;'>Tarmoq xatosi yuz berdi!</h3>"; 
            });
        };

        function drawTree(treeData) {
            const container = document.getElementById("tree-container");
            const width = container.clientWidth || window.innerWidth || 1000;
            const height = container.clientHeight || window.innerHeight || 800;

            const svgElement = d3.select("#tree-container").append("svg")
                .attr("width", "100%")
                .attr("height", "100%");

            // XATO SHU YERDA EDI: "g" o'zgaruvchisi alohida yaratildi
            const g = svgElement.append("g")
                .attr("transform", `translate(${width / 2}, 50)`);

            svgElement.call(d3.zoom().scaleExtent([0.15, 3]).on("zoom", (e) => {
                g.attr("transform", e.transform);
            }));

            const treeLayout = d3.tree().nodeSize([130, 270]); 
            const root = d3.hierarchy(treeData);
            
            treeLayout(root);

            root.descendants().forEach(d => {
                if(d.data.id !== "hidden_root") { window.personDB['p_' + d.data.id] = d.data; }
            });

            g.selectAll('.link').data(root.links()).enter().append('path')
                .attr('class', 'link')
                .attr('d', d3.linkVertical().x(d => d.x).y(d => d.y))
                .style('display', d => d.source.data.id === 'hidden_root' ? 'none' : null);

            const node = g.selectAll('.node').data(root.descendants()).enter().append('g')
                .attr('class', 'node')
                .attr('transform', d => `translate(${d.x},${d.y})`)
                .style('display', d => d.data.id === 'hidden_root' ? 'none' : null);

            node.append("foreignObject")
                .attr("width", 125) 
                .attr("height", d => d.data.spouse_name ? 270 : 140) 
                .attr("x", -62) 
                .attr("y", -45)
                .style("overflow", "visible")
                .append("xhtml:div")
                .html(d => {
                    let childBadge = d.data.child_order ? `<div class="order-badge">${d.data.child_order}</div>` : '';
                    let avatar1 = d.data.photo ? `<img src="${d.data.photo}" class="avatar">` : `<div class="avatar-placeholder">${d.data.gender === 'ayol' ? '👩' : '👨'}</div>`;
                    
                    let html = `<div class="card-wrapper" style="height: ${d.data.spouse_name ? '260px' : '130px'}">
                        ${childBadge}
                        <div class="shajara-card ${d.data.gender || 'erkak'}">
                            <div class="person-main" onclick="openProfile('p_${d.data.id}', false)">
                                ${avatar1}
                                <div class="person-name"><div>${d.data.first_name || d.data.name}</div><div>${d.data.last_name || ''}</div></div>
                                ${d.data.lifespan && d.data.lifespan !== "Noma'lum" ? `<div class="person-age">${d.data.lifespan} <br>${d.data.age ? '('+d.data.age+' yosh)' : ''}</div>` : ''}
                            </div>`;
                            
                    if (d.data.spouse_name) {
                        let avatar2 = d.data.spouse_photo ? `<img src="${d.data.spouse_photo}" class="avatar">` : `<div class="avatar-placeholder">${d.data.gender === 'ayol' ? '👨' : '👩'}</div>`;
                        html += `<div class="person-spouse" onclick="openProfile('p_${d.data.id}', true)">
                                <div class="spouse-icon">💍</div>
                                ${avatar2}
                                <div class="spouse-name"><div>${d.data.spouse_first_name}</div><div>${d.data.spouse_last_name}</div></div>
                                ${d.data.spouse_lifespan && d.data.spouse_lifespan !== "Noma'lum" ? `<div class="person-age">${d.data.spouse_lifespan} <br>${d.data.spouse_age ? '('+d.data.spouse_age+' yosh)' : ''}</div>` : ''}
                            </div>`;
                    }
                    html += `</div></div>`;
                    return html;
                });
        }

        function openProfile(pid, isSpouse) {
            const data = window.personDB[pid];
            if(!data) return;
            let name = isSpouse ? data.spouse_name : data.name;
            let photo = isSpouse ? data.spouse_photo : data.photo;
            let gender = isSpouse ? (data.gender === 'erkak' ? 'ayol' : 'erkak') : data.gender;
            let lifespan = isSpouse ? data.spouse_lifespan : data.lifespan;
            let age = isSpouse ? data.spouse_age : data.age;
            let prof = isSpouse ? data.spouse_profession : data.profession;
            let phone = isSpouse ? data.spouse_phone : data.phone;

            if(photo) { document.getElementById('mPhoto').innerHTML = `<img src="${photo}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`; } 
            else { document.getElementById('mPhoto').innerHTML = gender === 'ayol' ? '👩' : '👨'; }

            document.getElementById('mName').innerText = name;
            let dateText = lifespan !== "Noma'lum" ? lifespan : "Sana kiritilmagan";
            if (age) dateText += ` (${age} yosh)`;
            document.getElementById('mDates').innerText = dateText;
            document.getElementById('mProf').innerText = prof ? prof : "Kiritilmagan";
            
            if(phone) { document.getElementById('mPhone').innerHTML = `<a href="tel:${phone}" class="prof-link">${phone}</a>`; } 
            else { document.getElementById('mPhone').innerHTML = "<span style='color:#777;'>Kiritilmagan</span>"; }

            document.getElementById('profileModalOverlay').classList.add('active');
        }

        function closeProfile() { document.getElementById('profileModalOverlay').classList.remove('active'); }

        function exportTree() {
            const btn = document.getElementById('exportBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Tayyorlanmoqda...';

            if (typeof html2canvas === 'undefined') {
                const script = document.createElement('script');
                script.src = "https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js";
                script.onload = () => runHtml2Canvas(btn, originalText);
                document.head.appendChild(script);
            } else {
                runHtml2Canvas(btn, originalText);
            }
        }

        function runHtml2Canvas(btn, originalText) {
            const container = document.getElementById('tree-container');
            
            html2canvas(container, {
                backgroundColor: '#f4f7f6',
                scale: 2,
                useCORS: true
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                document.getElementById('exportedImage').src = imgData;
                document.getElementById('exportModalOverlay').classList.add('active');
                btn.innerHTML = originalText;
            }).catch(err => {
                alert("Xatolik: Rasmni yuklab bo'lmadi.");
                btn.innerHTML = originalText;
            });
        }

        function closeExport() { document.getElementById('exportModalOverlay').classList.remove('active'); }
    </script>
</body>
</html>