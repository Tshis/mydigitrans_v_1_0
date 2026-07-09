document.addEventListener("DOMContentLoaded",()=>{document.querySelectorAll("table.table.table-responsive").forEach((e,t)=>{let m=e.querySelector("tbody");if(m){let i=Array.from(m.querySelectorAll("tr:not(.no-result-row)")),c=1,s=10,d=[...i];var a=document.createElement("div"),n=(a.className="table-card",e.parentNode.insertBefore(a,e),document.createElement("div")),e=(n.className="table-search-bar-zone",n.innerHTML=`
            <div class="search-input-wrapper">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="custom-search-${t}" placeholder="Rechercher..." autocomplete="off">
            </div>
        `,a.appendChild(n),a.appendChild(e),document.createElement("div")),a=(e.className="table-pagination",e.innerHTML=`
            <div class="per-page-selector">
                <label for="per_page-${t}">Éléments par page :</label>
                <select id="per_page-${t}">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="pagination-nav">
                <button type="button" class="nav-btn" id="prev-${t}">
                    <i class="fa-solid fa-chevron-left"></i> Précédent
                </button>
                <div class="page-numbers" id="pages-${t}"></div>
                <button type="button" class="nav-btn" id="next-${t}">
                    Suivant <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        `,a.appendChild(e),n.querySelector("input")),n=e.querySelector("select");let p=e.querySelector("#prev-"+t),u=e.querySelector("#next-"+t),v=e.querySelector("#pages-"+t);function b(){var e=d.length,t=Math.ceil(e/s)||1,a=((c=c>t?t:c)-1)*s,n=a+s;i.forEach(e=>e.style.display="none"),d.slice(a,n).forEach(e=>{e.style.display=""});let l=m.querySelector(".no-result-row");0===e?l||((l=document.createElement("tr")).className="no-result-row",l.innerHTML=`
                        <td colspan="10" style="text-align: center; padding: 30px; color: var(--gray);">
                            <i class="fa-solid fa-circle-question" style="font-size: 1.5rem; margin-bottom: 8px; display: block;"></i>
                            Aucun résultat trouvé pour cette recherche.
                        </td>
                    `,m.appendChild(l)):l&&l.remove();var r=t;v.innerHTML="",p.disabled=1===c,u.disabled=c===r;for(let e=1;e<=r;e++){var o=document.createElement("button");o.type="button",o.className="page-item "+(e===c?"active":""),o.textContent=e,o.addEventListener("click",()=>{c=e,b()}),v.appendChild(o)}}a.addEventListener("input",e=>{let t=e.target.value.toLowerCase().trim();d=i.filter(e=>e.textContent.toLowerCase().includes(t)),c=1,b()}),n.addEventListener("change",e=>{s=parseInt(e.target.value,10),c=1,b()}),p.addEventListener("click",()=>{1<c&&(c--,b())}),u.addEventListener("click",()=>{var e=Math.ceil(d.length/s)||1;c<e&&(c++,b())}),b()}})});