<?php

use Illuminate\Support\Facades\DB;
use Webkul\CartRule\Models\CartRule;
use Webkul\CartRule\Models\CartRuleCoupon;
use Webkul\Checkout\Models\Cart;
use Webkul\Checkout\Models\CartAddress;
use Webkul\Checkout\Models\CartItem;
use Webkul\Checkout\Models\CartPayment;
use Webkul\Checkout\Models\CartShippingRate;
use Webkul\Faker\Helpers\Product as ProductFaker;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\InvoiceRepository;

use function Pest\Laravel\postJson;

/**
 * The whole storefront journey, run against the Dari installation the CI step
 * "Installing the store in Dari" prepares: browse, add to cart, apply a discount coupon, place the
 * order, invoice it, and read the invoice.
 *
 * Everything the customer sees is asserted twice: once as the number the shop calculated, and once
 * as the string the customer reads - which must be an Afghan amount, in Afghan digits, with the
 * sign of the Afghani in front of it.
 */
it('buys a product in Dari and prices the order, the discount and the invoice in afghanis', function () {
    /**
     * The installation this run was started against decides whether there is anything to check
     * here. The platform's own run installs an English store priced in dollars: phpunit.xml leaves
     * this file out of it, and this is what keeps it out of the way if that exclusion is ever lost.
     */
    if (app()->getLocale() !== 'fa_AF') {
        $this->markTestSkipped('this suite checks the store that is installed in Dari.');
    }

    expect(app()->getLocale())->toBe('fa_AF', 'this suite has to run against the Dari installation');

    // Arrange: one simple product at 1000 AFN, in an Afghan guest cart that ships for free.
    $product = (new ProductFaker([
        'attributes' => [
            5 => 'new',
            26 => 'guest_checkout',
            11 => 'price',
        ],

        'attribute_value' => [
            'new' => [
                'boolean_value' => true,
            ],

            'guest_checkout' => [
                'boolean_value' => true,
            ],

            'price' => [
                'float_value' => 1000,
            ],
        ],
    ]))
        ->getSimpleProductFactory()
        ->create();

    $unitPrice = (float) $product->price;

    expect($unitPrice)->toEqual(1000.0);

    $cart = Cart::factory()->create(['shipping_method' => 'free_free']);

    $additional = [
        'product_id' => $product->id,
        'rating' => '0',
        'is_buy_now' => '0',
        'quantity' => '1',
    ];

    CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'quantity' => 1,
        'name' => $product->name,
        'price' => $price = core()->convertPrice($unitPrice),
        'price_incl_tax' => $price,
        'base_price' => $unitPrice,
        'base_price_incl_tax' => $unitPrice,
        'total' => $price,
        'total_incl_tax' => $price,
        'base_total' => $unitPrice,
        'weight' => $product->weight ?? 0,
        'total_weight' => $product->weight ?? 0,
        'base_total_weight' => $product->weight ?? 0,
        'type' => $product->type,
        'additional' => $additional,
    ]);

    $billingAddress = CartAddress::factory()->create([
        'cart_id' => $cart->id,
        'address_type' => CartAddress::ADDRESS_TYPE_BILLING,
        'country' => 'AF',
        'state' => 'KAN',
        'city' => 'کندهار',
        'postcode' => '3801',
        'phone' => '+93 700 123 456',
    ]);

    $shippingAddress = CartAddress::factory()->create([
        'cart_id' => $cart->id,
        'address_type' => CartAddress::ADDRESS_TYPE_SHIPPING,
        'country' => 'AF',
        'state' => 'KAN',
        'city' => 'کندهار',
        'postcode' => '3801',
        'phone' => '+93 700 123 456',
    ]);

    CartPayment::factory()->create([
        'cart_id' => $cart->id,
        'method' => 'cashondelivery',
        'method_title' => core()->getConfigData('sales.payment_methods.cashondelivery.title'),
    ]);

    CartShippingRate::factory()->create([
        'carrier' => 'free',
        'carrier_title' => 'Free shipping',
        'method' => 'free_free',
        'method_title' => 'Free Shipping',
        'method_description' => 'Free Shipping',
        'cart_address_id' => $shippingAddress->id,
        'cart_id' => $cart->id,
    ]);

    // A ten percent discount, wired to the default channel and the guest customer group, the same
    // way the shop owner would set one up in the admin panel.
    $cartRule = CartRule::factory()->create([
        'name' => 'افغانستان ۱۰٪',
        'coupon_type' => 1,
        'action_type' => 'by_percent',
        'discount_amount' => 10,
        'status' => 1,
    ]);

    $cartRule->cart_rule_channels()->attach([core()->getCurrentChannel()->id]);
    $cartRule->cart_rule_customer_groups()->attach([1, 2, 3]);

    CartRuleCoupon::factory()->create([
        'cart_rule_id' => $cartRule->id,
        'code' => $couponCode = 'AFGHAN10',
        'is_primary' => 1,
    ]);

    cart()->setCart($cart);

    cart()->collectTotals();

    // Act and assert: the cart reads as an Afghan amount before any discount.
    $cart->refresh();

    $this->assertPrice(1000, $cart->sub_total);
    $this->assertPrice(1000, $cart->grand_total);

    expect(core()->formatPrice($cart->grand_total, 'AFN'))
        ->toBe('؋ ۱٬۰۰۰٫۰۰', 'the cart total has to reach the customer as an Afghan amount');

    // Act: apply the coupon.
    $response = postJson(route('shop.api.checkout.cart.coupon.apply'), [
        'code' => $couponCode,
    ])->assertOk();

    $response->assertJsonPath('data.coupon_code', $couponCode);

    $discountedTotal = (float) $response->json('data.grand_total');

    // Assert: ten percent of a thousand is a hundred, in Afghanis.
    $this->assertPrice(100, $response->json('data.discount_amount'));
    $this->assertPrice(900, $discountedTotal);

    expect(core()->formatPrice($discountedTotal, 'AFN'))->toBe('؋ ۹۰۰٫۰۰');

    // Act: place the order.
    postJson(route('shop.checkout.onepage.orders.store'))
        ->assertOk()
        ->assertJsonPath('data.redirect', true);

    $order = Order::query()->latest('id')->firstOrFail();

    // Assert: the order was booked in Afghanis, in every currency column it keeps.
    expect($order->base_currency_code)->toBe('AFN')
        ->and($order->order_currency_code)->toBe('AFN')
        ->and($order->channel_currency_code)->toBe('AFN');

    $this->assertPrice(100, $order->discount_amount);
    $this->assertPrice($discountedTotal, $order->sub_total);
    $this->assertPrice($discountedTotal, $order->grand_total);

    expect($order->grand_total_invoiced)->toEqual(0);

    // Act and assert: invoice the order and read what the customer would download.
    $orderItem = $order->items()->firstOrFail();

    $invoice = app(InvoiceRepository::class)->create([
        'order_id' => $order->id,
        'invoice' => [
            'items' => [
                $orderItem->id => $orderItem->qty_to_invoice,
            ],
        ],
    ]);

    expect($invoice->order_currency_code)->toBe('AFN');

    $html = view('shop::customers.account.orders.pdf', [
        'invoice' => $invoice,
        'orderCurrencyCode' => $invoice->order_currency_code,
    ])->render();

    // The invoice is a right-to-left document ...
    expect($html)->toContain('dir="rtl"');

    // ... typeset in a font that actually carries the letters and the sign (the storefront subset
    // cannot be used here: mPDF fetches the file over HTTP and needs the whole alphabet) ...
    expect($html)->toContain('NotoSansArabic-Regular.ttf');

    // ... written in Dari, not in English ...
    expect(trans('shop::app.customers.account.orders.invoice-pdf.invoice'))
        ->not->toBe(trans('shop::app.customers.account.orders.invoice-pdf.invoice', [], 'en'));

    expect($html)->toContain(trans('shop::app.customers.account.orders.invoice-pdf.invoice'));

    // ... and priced in Afghanis, with the amount the customer paid.
    expect($html)->toContain(core()->formatPrice($invoice->grand_total, 'AFN'));
    expect(core()->formatPrice($invoice->grand_total, 'AFN'))->toBe('؋ ۹۰۰٫۰۰');

    $this->assertPrice($discountedTotal, $invoice->grand_total);

    /**
     * And the page the customer is sent to once the order is placed: the order it names, the
     * tracking it promises, read from the right and written in Dari.
     */
    $this
        ->withSession(['order_id' => $order->id])
        ->get(route('shop.checkout.onepage.success'))
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee($order->increment_id)
        ->assertSee(trans('shop::app.checkout.success.thanks'), false)
        ->assertSee(trans('shop::app.checkout.success.info'), false);
});

it('keeps the shop priced in afghanis when a second order is placed the same day', function () {
    expect(DB::table('currencies')->where('code', 'AFN')->exists())->toBeTrue();

    expect(core()->getCurrentCurrency()->code)->toBe('AFN');
    expect(core()->getBaseCurrency()->code)->toBe('AFN');

    expect(core()->formatPrice(1234.5, 'AFN'))->toBe('؋ ۱٬۲۳۴٫۵۰');

    expect(core()->getCurrentLocale()->code)->toBe('fa_AF');
    expect(core()->getCurrentLocale()->direction)->toBe('rtl');
});
