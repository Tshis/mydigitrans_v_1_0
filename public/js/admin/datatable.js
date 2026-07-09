document.addEventListener("DOMContentLoaded",()=>{var e=document.getElementById("custom-table-search");let o=document.querySelector(".table tbody");if(e&&o){let n=o.querySelectorAll("tr");e.addEventListener("input",e=>{let t=e.target.value.toLowerCase().trim();n.forEach(e=>{e.textContent.toLowerCase().includes(t)?e.style.display="":e.style.display="none"});var r=Array.from(n).filter(e=>"none"!==e.style.display);let l=o.querySelector(".no-result-row");0===r.length?l||((l=document.createElement("tr")).className="no-result-row",l.innerHTML=`
                    <td colspan="10" style="text-align: center; padding: 30px; color: var(--gray);">
                        <i class="fa-solid fa-circle-question" style="font-size: 1.5rem; margin-bottom: 8px; display: block;"></i>
                        Aucune donnée ne correspond à votre recherche "${e.target.value}"
                    </td>
                `,o.appendChild(l)):l&&l.remove()})}});