document.addEventListener("DOMContentLoaded",()=>{console.log("layout_builder chargé");let p=document.querySelector(".admin-bus-layout-add-scope");if(p){let a=p.querySelector("#layout-main-rows"),l=p.querySelector("#layout-main-cols"),s=p.querySelector("#layout-back-extra"),c=p.querySelector("#layout-interactive-grid");var o=p.querySelector("#btn-generate-matrix");let t=p.querySelector("#special-cells-container");var d=p.querySelector("#btn-add-special-cell");let u=p.querySelector("#layout-aisle-toggle"),e=p.querySelector("#aisle-logic-block"),i=p.querySelector("#aisles-container");var r=p.querySelector("#btn-add-aisle");d&&t&&(d.addEventListener("click",()=>{let e=document.createElement("div");e.classList.add("js-special-cell","border-top-dashed"),e.innerHTML=`
                <button type="button" class="btn-remove-dynamic-row mb-10"><i class="fa-solid fa-trash"></i> Retirer</button>
                <div class="field mb-20">
                    <label class="admin-bus-layout-add-label">Type de la cellule</label>
                    <select class="admin-bus-layout-add-select dynamic-ctx-type" name="specialPositionType[]">
                        <option value="" disabled>Faites votre choix</option>
                        <option value="driver" selected>Chauffeur</option>
                        <option value="cashier">Receveur (Cashier)</option>
                        <option value="porter">Convoyeur (Porter)</option>
                        <option value="wc">Toilettes (WC)</option>
                        <option value="door">Portière / Escalier</option>
                        <option value="vip">Siège Passager VIP</option>
                        <option value="aisle">Allée vide</option>
                    </select>
                </div>
                <div class="field-group mb-5">
                    <div class="field">
                        <label class="admin-bus-layout-add-label">La rangée numéro</label>
                        <input name="specialPositionRow[]" type="number" class="admin-bus-layout-add-input dynamic-ctx-row" value="1" min="1" max="25">
                    </div>
                    <div class="field">
                        <label class="admin-bus-layout-add-label">Colonne numéro</label>
                        <input name="specialPositionCol[]" type="number" class="admin-bus-layout-add-input dynamic-ctx-col" value="1" min="1" max="7">
                    </div>
                </div>
            `,e.querySelector(".btn-remove-dynamic-row").addEventListener("click",()=>e.remove()),t.appendChild(e)}),d.click()),u&&e&&(u.addEventListener("change",()=>{e.style.display=u.checked?"block":"none"}),r.addEventListener("click",()=>{let e=document.createElement("div");e.classList.add("field","mb-5","border-top-dashed"),e.style.paddingTop="10px",e.innerHTML=`
                <button type="button" class="btn-remove-dynamic-row"><i class="fa-solid fa-trash"></i></button>
                <label class="admin-bus-layout-add-label">Après la colonne :</label>
                <input name="aisles[]" placeholder="Ex : 2" class="admin-bus-layout-add-input dynamic-aisle-pos" type="number" min="1" max="7">
            `,e.querySelector(".btn-remove-dynamic-row").addEventListener("click",()=>e.remove()),i.appendChild(e)}),r.click(),d=i.querySelector(".dynamic-aisle-pos"))&&(d.value=2),o.addEventListener("click",function(){if(c){var o=parseInt(a.value)||0,e=parseInt(l.value)||0;let t=[];u.checked&&p.querySelectorAll(".dynamic-aisle-pos").forEach(e=>{e=parseInt(e.value);0<e&&t.push(e)});var d=e+t.length;c.innerHTML="",c.style.setProperty("--grid-cols",d);let i=[];p.querySelectorAll(".js-special-cell").forEach(e=>{var a=e.querySelector(".dynamic-ctx-type").value,l=parseInt(e.querySelector(".dynamic-ctx-row").value),e=parseInt(e.querySelector(".dynamic-ctx-col").value);0<l&&0<e&&i.push({r:l,c:e,type:a})});for(let l=1;l<=o;l++){let a=0;for(let e=1;e<=d;e++){var r=document.createElement("div"),n=(r.classList.add("cell-unit"),r.setAttribute("data-row",l),l===o);!t.includes(a)||n&&s.checked?(a++,r.setAttribute("data-type","seat"),r.setAttribute("data-col",a),(n=i.find(e=>e.r===l&&e.c===a))&&r.setAttribute("data-type",n.type),c.appendChild(r)):(r.setAttribute("data-type","aisle"),c.appendChild(r),t.splice(t.indexOf(a),1))}u.checked&&p.querySelectorAll(".dynamic-aisle-pos").forEach(e=>{e=parseInt(e.value);0<e&&!t.includes(e)&&t.push(e)})}{let e=c.querySelectorAll(".cell-unit"),t=1;e.forEach(e=>{var a,l=e.getAttribute("data-type");e.innerHTML="","seat"===l||"vip"===l?((a=document.createElement("span")).classList.add("-number"),a.textContent=t++,e.appendChild(a)):(a=document.createElement("i"),"driver"===l?(a.className="fa-solid fa-id-card",e.appendChild(a)):"wc"===l?(a.className="fa-solid fa-restroom",e.appendChild(a)):"door"===l?(a.className="fa-solid fa-door-open",e.appendChild(a)):"cashier"===l?(a.className="fa-solid fa-hand-holding-dollar",e.appendChild(a)):"porter"===l&&(a.className="fa-solid fa-image-portrait",e.appendChild(a)),"aisle"!==l&&e.appendChild(a))})}}})}});