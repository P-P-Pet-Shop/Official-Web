import{t as e}from"./api.CeE1PEmJ.js";async function t(t,r=``,i=``){let a=document.getElementById(`track-error`),o=document.getElementById(`track-results`);a&&a.classList.add(`hidden`);let s=`/orders/${encodeURIComponent(t)}/tracking?`;i&&(s+=`token=${encodeURIComponent(i)}`),r&&(s+=`&phone=${encodeURIComponent(r)}`);let c=await e(s);c.success&&c.data?(o&&o.classList.remove(`hidden`),n(c.data)):(o&&o.classList.add(`hidden`),a&&(a.textContent=c.message||`Unable to locate order. Please check order number or mobile number.`,a.classList.remove(`hidden`)))}function n(e){let t=document.getElementById(`res-order-num`),n=document.getElementById(`res-date`),r=document.getElementById(`res-status-badge`),i=document.getElementById(`res-items`),a=document.getElementById(`res-timeline`);t&&(t.textContent=e.order_number),n&&(n.textContent=`Placed on ${e.created_at} | Destination: ${e.delivery_city}, ${e.delivery_district}`),r&&(r.textContent=e.website_order_status);let o={PENDING:1,VALIDATING:1,CONFIRMED:2,PREPARING:3,READY_FOR_PICKUP:4,PICKED_UP:4,DELIVERED:4}[e.website_order_status]||1;document.querySelectorAll(`.step-col`).forEach(e=>{let t=parseInt(e.dataset.step||`1`,10),n=e.querySelector(`.step-circle`),r=e.querySelector(`.step-label`);t<=o?(n?.classList.remove(`bg-gray-100`,`text-gray-500`),n?.classList.add(`bg-[#D9782D]`,`text-white`,`shadow-sm`),r?.classList.add(`text-[#D9782D]`)):(n?.classList.add(`bg-gray-100`,`text-gray-500`),n?.classList.remove(`bg-[#D9782D]`,`text-white`),r?.classList.remove(`text-[#D9782D]`))}),i&&(i.innerHTML=e.items.map(e=>`
        <div class="flex justify-between items-center text-xs py-1 border-b border-gray-100 last:border-none">
          <div>
            <span class="font-bold text-gray-900">${e.product_name}</span>
            ${e.variant_name?`<span class="text-gray-500"> (${e.variant_name})</span>`:``}
            <span class="text-gray-400 block font-mono">Qty: ${e.quantity}</span>
          </div>
          <div class="font-black text-gray-900">${e.subtotal_formatted}</div>
        </div>
      `).join(``)+`
        <div class="pt-3 border-t border-gray-200 flex justify-between items-baseline text-xs font-bold text-gray-800">
          <span>Total:</span>
          <span class="text-base font-black text-[#D9782D]">${e.total_amount_formatted} (${e.payment_method})</span>
        </div>
      `),a&&(a.innerHTML=e.timeline.map(e=>`
        <div class="relative pl-4 text-xs space-y-0.5">
          <div class="w-2.5 h-2.5 rounded-full bg-[#D9782D] absolute -left-[7px] top-1"></div>
          <span class="font-bold text-gray-900 block">${e.status}</span>
          <span class="text-gray-500 block">${e.description}</span>
          <span class="text-[10px] text-gray-400">${e.created_at}</span>
        </div>
      `).join(``))}document.addEventListener(`DOMContentLoaded`,()=>{let e=new URLSearchParams(window.location.search),n=e.get(`order`),r=e.get(`token`);if(n){let e=document.getElementById(`track-order-num-input`);e&&(e.value=n),t(n,``,r||``)}document.getElementById(`track-form`)?.addEventListener(`submit`,e=>{e.preventDefault();let n=document.getElementById(`track-order-num-input`).value.trim(),i=document.getElementById(`track-phone-input`).value.trim();n&&t(n,i,r||``)})});