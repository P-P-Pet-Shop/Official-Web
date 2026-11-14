import{a as e,o as t,r as n}from"./cart.BtWZmMq-.js";import{t as r}from"./api.CeE1PEmJ.js";async function i(){let a=n(),o=document.getElementById(`cart-items-wrapper`),s=document.getElementById(`empty-cart-view`),c=document.getElementById(`order-summary-box`),l=document.getElementById(`district-select`)?.value||`Colombo`;if(a.length===0){o&&(o.innerHTML=``),s&&s.classList.remove(`hidden`),c&&c.classList.add(`hidden`);return}s&&s.classList.add(`hidden`),c&&c.classList.remove(`hidden`);let u=(await r(`/cart/validate`,{method:`POST`,body:JSON.stringify({items:a.map(e=>({product_id:e.productId,variant_id:e.variantId,quantity:e.quantity})),district:l})})).data||{},d=u.items||a.map(e=>({product_id:e.productId,variant_id:e.variantId,product_name:e.name,variant_name:e.variantName,unit_price:e.price,quantity:e.quantity,image_url:e.image,subtotal:e.price*e.quantity}));o&&(o.innerHTML=d.map(e=>`
        <div class="bg-white rounded-3xl p-5 border border-gray-100 shadow-sm flex flex-col sm:flex-row items-center gap-5">
          <img src="${e.image_url||`/assets/100002.png`}" alt="${e.product_name}" class="w-24 h-24 object-cover rounded-2xl bg-gray-50 shrink-0" />
          <div class="flex-1 space-y-1 text-center sm:text-left">
            <h3 class="font-bold text-gray-900 text-base">${e.product_name}</h3>
            ${e.variant_name?`<span class="inline-block bg-gray-100 text-gray-600 text-xs px-2.5 py-0.5 rounded-full font-medium">${e.variant_name}</span>`:``}
            <div class="text-sm font-semibold text-gray-500">Unit: Rs. ${e.unit_price.toLocaleString(`en-US`,{minimumFractionDigits:2})}</div>
          </div>
          
          <!-- Quantity Controls -->
          <div class="flex items-center gap-4">
            <div class="flex items-center border border-gray-200 rounded-xl p-1 bg-gray-50">
              <button class="cart-minus w-8 h-8 flex items-center justify-center font-bold text-gray-600 hover:bg-white rounded-lg transition" data-id="${e.product_id}" data-variant="${e.variant_id??``}">-</button>
              <span class="w-10 text-center text-xs font-black">${e.quantity}</span>
              <button class="cart-plus w-8 h-8 flex items-center justify-center font-bold text-gray-600 hover:bg-white rounded-lg transition" data-id="${e.product_id}" data-variant="${e.variant_id??``}">+</button>
            </div>
            
            <div class="text-right w-24">
              <div class="text-base font-black text-gray-900">Rs. ${(e.subtotal||e.unit_price*e.quantity).toLocaleString(`en-US`,{minimumFractionDigits:2})}</div>
            </div>

            <button class="cart-remove text-gray-400 hover:text-red-500 p-2 transition" data-id="${e.product_id}" data-variant="${e.variant_id??``}" title="Remove item">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
          </div>
        </div>
      `).join(``),o.querySelectorAll(`.cart-minus`).forEach(e=>{e.addEventListener(`click`,e=>{let n=e.currentTarget,r=parseInt(n.dataset.id||`0`,10),o=n.dataset.variant?parseInt(n.dataset.variant,10):null,s=a.find(e=>e.productId===r&&(e.variantId??null)===o);s&&(t(r,o,s.quantity-1),i())})}),o.querySelectorAll(`.cart-plus`).forEach(e=>{e.addEventListener(`click`,e=>{let n=e.currentTarget,r=parseInt(n.dataset.id||`0`,10),o=n.dataset.variant?parseInt(n.dataset.variant,10):null,s=a.find(e=>e.productId===r&&(e.variantId??null)===o);s&&(t(r,o,s.quantity+1),i())})}),o.querySelectorAll(`.cart-remove`).forEach(t=>{t.addEventListener(`click`,t=>{let n=t.currentTarget;e(parseInt(n.dataset.id||`0`,10),n.dataset.variant?parseInt(n.dataset.variant,10):null),i()})}));let f=u.subtotal??a.reduce((e,t)=>e+t.price*t.quantity,0),p=u.delivery_fee??(f>=15e3?0:350),m=u.total??f+p,h=document.getElementById(`summary-subtotal`),g=document.getElementById(`summary-delivery`),_=document.getElementById(`summary-total`);h&&(h.textContent=`Rs. ${f.toLocaleString(`en-US`,{minimumFractionDigits:2})}`),g&&(g.textContent=p===0?`FREE`:`Rs. ${p.toLocaleString(`en-US`,{minimumFractionDigits:2})}`),_&&(_.textContent=`Rs. ${m.toLocaleString(`en-US`,{minimumFractionDigits:2})}`)}document.addEventListener(`DOMContentLoaded`,()=>{i(),document.getElementById(`district-select`)?.addEventListener(`change`,i)});