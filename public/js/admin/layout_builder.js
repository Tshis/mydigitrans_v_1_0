document.addEventListener("DOMContentLoaded",()=>{let y=document.querySelector(".admin-bus-layout-add-scope");if(y){let t=y.querySelector("#layout-main-rows"),l=y.querySelector("#layout-main-cols"),s=y.querySelector("#layout-back-extra"),u=y.querySelector("#layout-interactive-grid");var r=y.querySelector("#btn-generate-matrix");let a=y.querySelector("#special-cells-container");var c=y.querySelector("#btn-add-special-cell");let p=y.querySelector("#layout-aisle-toggle"),e=y.querySelector("#aisle-logic-block"),i=y.querySelector("#aisles-container");var d=y.querySelector("#btn-add-aisle");function o(){if(u){var c=parseInt(t.value)||11,e=parseInt(l.value)||4;let i=[];p.checked&&y.querySelectorAll(".dynamic-aisle-pos").forEach(e=>{e=parseInt(e.value);0<e&&i.push(e)});var d=e+i.length;u.innerHTML="",u.style.setProperty("--grid-cols",d);let r=[];y.querySelectorAll(".js-special-cell").forEach(e=>{var t=e.querySelector(".dynamic-ctx-type").value,l=parseInt(e.querySelector(".dynamic-ctx-row").value),e=parseInt(e.querySelector(".dynamic-ctx-col").value);0<l&&0<e&&r.push({r:l,c:e,type:t})});for(let a=1;a<=c;a++){let t=0,l=0;for(let e=1;e<=d;e++){var o=document.createElement("div"),n=(o.classList.add("cell-unit"),o.setAttribute("data-row",a),a===c);!i.includes(t)||n&&s.checked?(t++,o.setAttribute("data-type","seat"),o.setAttribute("data-col",t),(n=r.find(e=>e.r===a&&e.c===t))&&o.setAttribute("data-type",n.type),u.appendChild(o)):(o.setAttribute("data-type","aisle"),u.appendChild(o),i.splice(i.indexOf(t),1),l++)}p.checked&&y.querySelectorAll(".dynamic-aisle-pos").forEach(e=>{e=parseInt(e.value);0<e&&!i.includes(e)&&i.push(e)})}{let e=u.querySelectorAll(".cell-unit"),l=1;e.forEach(e=>{var t=e.getAttribute("data-type");e.textContent="seat"===t||"vip"===t?l++:"driver"===t?"CH":"wc"===t?"WC":"door"===t?"PORT":""})}}}c&&a&&(c.addEventListener("click",()=>{let e=document.createElement("div");e.classList.add("js-special-cell","border-top-dashed"),e.innerHTML=`
                <button type="button" class="btn-remove-dynamic-row"><i class="fa-solid fa-trash"></i> Retirer</button>
                <div class="field mb-20">
                  <label class="admin-bus-layout-add-label">Type de la cellule</label>
                  <select class="admin-bus-layout-add-select dynamic-ctx-type">
                    <option value="driver">Chauffeur</option>
                    <option value="wc">Toilettes (WC)</option>
                    <option value="door">Portière / Escalier</option>
                    <option value="vip">Siège Passager VIP</option>
                    <option value="aisle">Allée vide</option>
                  </select>
                </div>
                <div class="field-group mb-5">
                  <div class="field">
                    <label class="admin-bus-layout-add-label">La rangée numéro</label>
                    <input type="number" class="admin-bus-layout-add-input dynamic-ctx-row" value="1" min="1" max="25">
                  </div>
                  <div class="field">
                    <label class="admin-bus-layout-add-label">Colonne numéro</label>
                    <input type="number" class="admin-bus-layout-add-input dynamic-ctx-col" value="1" min="1" max="7">
                  </div>
                </div>
            `,e.querySelector(".btn-remove-dynamic-row").addEventListener("click",()=>e.remove()),a.appendChild(e)}),c.click()),p&&e&&(p.addEventListener("change",()=>{e.style.display=p.checked?"block":"none"}),d.addEventListener("click",()=>{let e=document.createElement("div");e.classList.add("field","mb-5","border-top-dashed"),e.style.paddingTop="10px",e.innerHTML=`
                <button type="button" class="btn-remove-dynamic-row"><i class="fa-solid fa-trash"></i></button>
                <label class="admin-bus-layout-add-label">Après la colonne :</label>
                <input placeholder="Ex : 2" class="admin-bus-layout-add-input dynamic-aisle-pos" type="number" min="1" max="6">
            `,e.querySelector(".btn-remove-dynamic-row").addEventListener("click",()=>e.remove()),i.appendChild(e)}),d.click(),c=i.querySelector(".dynamic-aisle-pos"))&&(c.value=2),r.addEventListener("click",o),o()}});