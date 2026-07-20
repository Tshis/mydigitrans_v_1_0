document.addEventListener("DOMContentLoaded",()=>{document.querySelectorAll("table.js-datatable").forEach((e,t)=>{let m=e.querySelector("tbody");if(m){let o=Array.from(m.querySelectorAll("tr:not(.no-result-row)")),s=1,c=10,d=[...o];var a=document.createElement("div"),n=(a.className="table-card",e.parentNode.insertBefore(a,e),document.createElement("div")),l=(n.className="table-search-bar-zone",n.innerHTML=`
            <div class="search-input-wrapper">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="custom-search-${t}" placeholder="Rechercher dans le tableau..." autocomplete="off">
            </div>
        `,a.appendChild(n),document.createElement("div")),l=(l.className="table-responsive-wrapper",a.appendChild(l),l.appendChild(e),document.createElement("div")),e=(l.className="table-pagination",l.innerHTML=`
            <div class="per-page-selector">
                <label for="per_page-${t}">Éléments par page :</label>
                <select id="per_page-${t}">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
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
        `,a.appendChild(l),n.querySelector("input")),a=l.querySelector("select");let p=l.querySelector("#prev-"+t),u=l.querySelector("#next-"+t),v=l.querySelector("#pages-"+t);function b(){var e=d.length,t=Math.ceil(e/c)||1,a=((s=s>t?t:s)-1)*c,n=a+c;o.forEach(e=>e.classList.add("is-hidden")),d.slice(a,n).forEach(e=>{e.classList.remove("is-hidden")});let l=m.querySelector(".no-result-row");0===e?l||((l=document.createElement("tr")).className="no-result-row",l.innerHTML=`
                        <td colspan="20" style="text-align: center; padding: 2.5rem; color: #a0aec0;">
                            <i class="fa-solid fa-circle-question" style="font-size: 1.6rem; margin-bottom: 8px; display: block;"></i>
                            Aucune correspondance trouvée.
                        </td>
                    `,m.appendChild(l)):l&&l.remove();var r=t;v.innerHTML="",p.disabled=1===s,u.disabled=s===r;for(let e=1;e<=r;e++){var i=document.createElement("button");i.type="button",i.className="page-item "+(e===s?"active":""),i.textContent=e,i.addEventListener("click",()=>{s=e,b()}),v.appendChild(i)}}e.addEventListener("input",e=>{let t=e.target.value.toLowerCase().trim();d=o.filter(e=>e.textContent.toLowerCase().includes(t)),s=1,b()}),a.addEventListener("change",e=>{c=parseInt(e.target.value,10),s=1,b()}),p.addEventListener("click",()=>{1<s&&(s--,b())}),u.addEventListener("click",()=>{var e=Math.ceil(d.length/c)||1;s<e&&(s++,b())}),b()}})});