import{t as e}from"./api.CeE1PEmJ.js";function t(){let e=localStorage.getItem(`pandp_admin_token`),t=document.getElementById(`login-modal`);e?(t?.classList.add(`hidden`),n()):t?.classList.remove(`hidden`)}document.querySelectorAll(`.tab-btn`).forEach(e=>{e.addEventListener(`click`,e=>{let t=e.currentTarget,n=t.dataset.tab;document.querySelectorAll(`.tab-btn`).forEach(e=>{e.classList.remove(`bg-[#D9782D]`,`text-white`,`shadow-sm`),e.classList.add(`bg-white`,`text-gray-700`,`border`,`border-gray-200`)}),t.classList.remove(`bg-white`,`text-gray-700`,`border`,`border-gray-200`),t.classList.add(`bg-[#D9782D]`,`text-white`,`shadow-sm`),document.querySelectorAll(`.tab-content`).forEach(e=>e.classList.add(`hidden`)),document.getElementById(`tab-${n}`)?.classList.remove(`hidden`),n===`orders`&&r(),n===`products`&&a(),n===`inventory`&&o(),n===`uber`&&s()})});async function n(){let t=await e(`/admin/dashboard`);if(t.success&&t.data){let e=t.data.metrics;document.getElementById(`metric-orders`).textContent=e.total_orders,document.getElementById(`metric-pending`).textContent=`${e.pending_orders} pending action`,document.getElementById(`metric-revenue`).textContent=e.total_revenue_formatted,document.getElementById(`metric-products`).textContent=e.total_products,document.getElementById(`metric-lowstock`).textContent=`${e.low_stock_products} low stock alerts`,document.getElementById(`metric-uber-synced`).textContent=`${e.uber_synced_products} synced products`;let n=document.getElementById(`recent-logs-table`);n&&(n.innerHTML=(t.data.recent_sync_logs||[]).map(e=>`
          <div class="flex justify-between items-center py-1.5 border-b border-gray-100 last:border-none">
            <div>
              <span class="font-bold text-gray-800">[${e.sync_type}]</span>
              <span class="text-gray-500">Processed: ${e.items_processed}, Updated: ${e.items_updated}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="font-semibold ${e.status===`SUCCESS`?`text-green-600`:`text-red-500`}">${e.status}</span>
              <span class="text-gray-400">${e.started_at}</span>
            </div>
          </div>
        `).join(``)||`<p class="text-gray-400">No recent synchronization events recorded.</p>`)}}async function r(){let t=await e(`/admin/orders`),n=document.getElementById(`orders-tbody`);t.success&&t.data&&n&&(n.innerHTML=t.data.map(e=>`
        <tr class="hover:bg-gray-50">
          <td class="py-3 px-4 font-mono font-bold">${e.order_number}</td>
          <td class="py-3 px-4 text-gray-500">${e.created_at}</td>
          <td class="py-3 px-4 text-gray-900">${e.customer_name} <span class="text-gray-400 block">${e.customer_phone}</span></td>
          <td class="py-3 px-4 font-black">Rs. ${parseFloat(e.total_amount).toLocaleString(`en-US`,{minimumFractionDigits:2})}</td>
          <td class="py-3 px-4">${e.payment_method}</td>
          <td class="py-3 px-4">
            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase ${i(e.website_order_status)}">${e.website_order_status}</span>
          </td>
          <td class="py-3 px-4 text-right">
            <select class="order-status-change bg-white border border-gray-200 rounded-lg p-1 text-xs" data-id="${e.id}">
              <option value="PENDING" ${e.website_order_status===`PENDING`?`selected`:``}>PENDING</option>
              <option value="CONFIRMED" ${e.website_order_status===`CONFIRMED`?`selected`:``}>CONFIRMED</option>
              <option value="PREPARING" ${e.website_order_status===`PREPARING`?`selected`:``}>PREPARING</option>
              <option value="READY_FOR_PICKUP" ${e.website_order_status===`READY_FOR_PICKUP`?`selected`:``}>READY_FOR_PICKUP</option>
              <option value="DELIVERED" ${e.website_order_status===`DELIVERED`?`selected`:``}>DELIVERED</option>
              <option value="CANCELLED" ${e.website_order_status===`CANCELLED`?`selected`:``}>CANCELLED</option>
            </select>
          </td>
        </tr>
      `).join(``),n.querySelectorAll(`.order-status-change`).forEach(t=>{t.addEventListener(`change`,async t=>{let n=t.currentTarget,i=n.dataset.id,a=n.value;await e(`/admin/orders/${i}`,{method:`PATCH`,body:JSON.stringify({status:a})}),r()})}))}function i(e){return e===`CONFIRMED`||e===`DELIVERED`?`bg-green-100 text-green-800`:e===`PREPARING`||e===`READY_FOR_PICKUP`?`bg-blue-100 text-blue-800`:e===`CANCELLED`||e===`REJECTED`?`bg-red-100 text-red-800`:`bg-amber-100 text-amber-800`}async function a(){let t=await e(`/admin/products`),n=document.getElementById(`products-tbody`);t.success&&t.data&&n&&(n.innerHTML=t.data.map(e=>`
        <tr class="hover:bg-gray-50">
          <td class="py-3 px-4 font-bold text-gray-900">${e.name}</td>
          <td class="py-3 px-4 text-gray-500">${e.category_name}</td>
          <td class="py-3 px-4 font-mono text-gray-400">${e.sku}</td>
          <td class="py-3 px-4 font-black">Rs. ${parseFloat(e.base_price).toLocaleString(`en-US`,{minimumFractionDigits:2})}</td>
          <td class="py-3 px-4">
            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${e.is_active?`bg-green-100 text-green-700`:`bg-gray-100 text-gray-600`}">
              ${e.is_active?`ACTIVE`:`INACTIVE`}
            </span>
          </td>
          <td class="py-3 px-4 text-right">
            <a href="/shop/${e.slug}" target="_blank" class="text-[#D9782D] hover:underline font-bold">View</a>
          </td>
        </tr>
      `).join(``))}async function o(){let t=await e(`/admin/inventory`),n=document.getElementById(`inventory-tbody`);t.success&&t.data&&n&&(n.innerHTML=t.data.map(e=>`
        <tr class="hover:bg-gray-50">
          <td class="py-3 px-4 font-bold">${e.product_name} ${e.variant_name?`<span class="text-gray-400">(${e.variant_name})</span>`:``}</td>
          <td class="py-3 px-4 font-mono text-gray-400">${e.product_sku}</td>
          <td class="py-3 px-4 font-bold text-gray-900">${e.quantity_on_hand}</td>
          <td class="py-3 px-4 text-gray-500">${e.quantity_reserved}</td>
          <td class="py-3 px-4 font-black text-green-700">${e.quantity_on_hand-e.quantity_reserved}</td>
          <td class="py-3 px-4 text-gray-400">${e.reorder_level}</td>
          <td class="py-3 px-4 text-right">
            <input type="number" class="inv-update-input w-16 p-1 border border-gray-200 rounded text-center" value="${e.quantity_on_hand}" data-id="${e.id}" />
            <button class="inv-save-btn bg-gray-900 text-white px-2 py-1 rounded ml-1 text-[11px]" data-id="${e.id}">Save</button>
          </td>
        </tr>
      `).join(``),n.querySelectorAll(`.inv-save-btn`).forEach(t=>{t.addEventListener(`click`,async t=>{let r=t.currentTarget.dataset.id,i=n.querySelector(`.inv-update-input[data-id="${r}"]`);i&&(await e(`/admin/inventory/${r}`,{method:`PATCH`,body:JSON.stringify({quantity_on_hand:parseInt(i.value,10)})}),o())})}))}async function s(){let t=await e(`/admin/uber/mappings`),n=document.getElementById(`uber-mappings-tbody`);t.success&&t.data&&n&&(n.innerHTML=t.data.map(e=>`
        <tr class="hover:bg-gray-50">
          <td class="py-3 px-4 font-bold">${e.product_name}</td>
          <td class="py-3 px-4 font-mono text-gray-500">${e.uber_item_id}</td>
          <td class="py-3 px-4 font-bold">Rs. ${parseFloat(e.uber_price).toLocaleString(`en-US`,{minimumFractionDigits:2})}</td>
          <td class="py-3 px-4">
            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${e.uber_is_available?`bg-green-100 text-green-700`:`bg-red-100 text-red-700`}">
              ${e.uber_is_available?`AVAILABLE`:`OUT_OF_STOCK`}
            </span>
          </td>
          <td class="py-3 px-4 text-gray-400">${e.last_synced_at||`Never`}</td>
        </tr>
      `).join(``)||`<tr><td colspan="5" class="py-4 text-center text-gray-400">No Uber mappings established yet. Trigger sync to match items.</td></tr>`)}document.getElementById(`uber-sync-all-btn`)?.addEventListener(`click`,async()=>{let t=document.getElementById(`sync-feedback`);t&&(t.textContent=`Triggering synchronization with Uber Eats...`,t.className=`text-xs font-semibold p-3 rounded-xl bg-blue-50 text-blue-700 block`);let n=await e(`/admin/uber/sync`,{method:`POST`,body:JSON.stringify({type:`ALL`})});t&&(t.textContent=n.message||`Sync completed successfully!`,t.className=`text-xs font-semibold p-3 rounded-xl bg-green-50 text-green-700 block`),s()}),document.getElementById(`admin-login-form`)?.addEventListener(`submit`,async t=>{t.preventDefault();let r=document.getElementById(`admin-username`).value,i=document.getElementById(`admin-password`).value,a=document.getElementById(`login-error`),o=await e(`/admin/auth/login`,{method:`POST`,body:JSON.stringify({username:r,password:i})});o.success&&o.data?.token?(localStorage.setItem(`pandp_admin_token`,o.data.token),document.getElementById(`login-modal`)?.classList.add(`hidden`),n()):a&&(a.textContent=o.message||`Invalid username or password.`,a.classList.remove(`hidden`))}),document.getElementById(`admin-logout-btn`)?.addEventListener(`click`,()=>{localStorage.removeItem(`pandp_admin_token`),window.location.reload()}),document.addEventListener(`DOMContentLoaded`,t);