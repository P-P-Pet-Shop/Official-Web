/**
 * P and P Pet Shop - Client-Side Cart State Manager
 * Synchronizes with localStorage and provides reactive event dispatching.
 */

export interface CartItem {
  productId: number;
  variantId?: number | null;
  name: string;
  variantName?: string | null;
  sku: string;
  slug: string;
  price: number;
  image: string;
  quantity: number;
}

const CART_STORAGE_KEY = 'pandp_petshop_cart';

export function getCart(): CartItem[] {
  if (typeof window === 'undefined') return [];
  try {
    const data = localStorage.getItem(CART_STORAGE_KEY);
    return data ? JSON.parse(data) : [];
  } catch (e) {
    console.error('Failed reading cart from localStorage', e);
    return [];
  }
}

export function saveCart(cart: CartItem[]): void {
  if (typeof window === 'undefined') return;
  try {
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
    window.dispatchEvent(new CustomEvent('cart-updated', { detail: { cart } }));
  } catch (e) {
    console.error('Failed saving cart to localStorage', e);
  }
}

export function addToCart(item: Omit<CartItem, 'quantity'> & { quantity?: number }): void {
  const cart = getCart();
  const quantity = item.quantity ?? 1;

  const existingIndex = cart.findIndex(
    (c) => c.productId === item.productId && (c.variantId ?? null) === (item.variantId ?? null)
  );

  if (existingIndex > -1) {
    cart[existingIndex].quantity += quantity;
  } else {
    cart.push({
      productId: item.productId,
      variantId: item.variantId ?? null,
      name: item.name,
      variantName: item.variantName ?? null,
      sku: item.sku,
      slug: item.slug,
      price: item.price,
      image: item.image,
      quantity,
    });
  }

  saveCart(cart);
}

export function removeFromCart(productId: number, variantId?: number | null): void {
  const cart = getCart().filter(
    (c) => !(c.productId === productId && (c.variantId ?? null) === (variantId ?? null))
  );
  saveCart(cart);
}

export function updateQuantity(productId: number, variantId: number | null | undefined, quantity: number): void {
  const cart = getCart();
  const item = cart.find(
    (c) => c.productId === productId && (c.variantId ?? null) === (variantId ?? null)
  );

  if (item) {
    if (quantity <= 0) {
      removeFromCart(productId, variantId);
      return;
    }
    item.quantity = quantity;
    saveCart(cart);
  }
}

export function clearCart(): void {
  saveCart([]);
}

export function getCartTotalCount(): number {
  return getCart().reduce((acc, item) => acc + item.quantity, 0);
}

export function getCartSubtotal(): number {
  return getCart().reduce((acc, item) => acc + item.price * item.quantity, 0);
}

