document.addEventListener("DOMContentLoaded",()=>{console.log("Script d'interconnexion AJAX Seatmap chargé");var e=document.querySelector(".admin-agency-bus-add-container");if(e){let r=e.querySelector("#bus-layout-selector"),a=e.querySelector("#seatmap-html-receiver"),n=e.querySelector("#seatmap-live-title");r&&a&&r.addEventListener("change",()=>{var e=r.value;let t=r.options[r.selectedIndex].text;a.innerHTML=`
                <div class="text-center text-gray" style="padding: 40px 10px;">
                    <i class="fa-solid fa-spinner fa-spin text-primary" style="font-size: 24px; margin-bottom: 10px;"></i>
                    <br>Génération du plan d'aménagement...
                </div>
            `,fetch("/admin/agency/bus/preview-layout/"+e).then(e=>{if(e.ok)return e.text();throw new Error("Erreur de récupération du gabarit.")}).then(e=>{a.innerHTML=e,n&&(n.textContent=t),a.querySelectorAll(".seat-radio").forEach(e=>{e.setAttribute("disabled","disabled"),e.style.cursor="default"})}).catch(e=>{console.error(e),a.innerHTML=`
                        <div class="text-center text-danger" style="padding: 30px 10px; font-size: 12px; background:rgba(220,53,69,0.05); border:1px solid rgba(220,53,69,0.15); border-radius:8px;">
                            <i class="fa-solid fa-circle-exclamation mb-5" style="font-size: 18px;"></i>
                            <br>Échec du chargement du plan. Veuillez réessayer.
                        </div>
                    `})})}});