<?php

/**
 * Bachat route map.
 * OWNERS: A = Auth+Shop agent, B = Campaigns/Procurement agent, C = Services/Delivery agent,
 *         D = Admin panels agent, E = Community agent. [core] = built by coordinator.
 */

use App\Core\Router as R;

/* ============================ PUBLIC ============================ */

R::get('/', 'HomeController@index');                                   // [core]
R::get('/society/{slug}', 'HomeController@society');                   // [core]
R::get('/lang/{code}', 'HomeController@lang');                         // [core]

/* -------- Auth (A) -------- */
R::get('/login', 'AuthController@showLogin');                          // A
R::post('/login', 'AuthController@login');                             // A
R::get('/join', 'AuthController@showJoin');                            // A
R::post('/join', 'AuthController@join');                               // A
R::get('/join/pending', 'AuthController@pending');                     // A
R::get('/vendor/register', 'VendorController@showRegister');           // B (owns whole VendorController incl. public register)
R::post('/vendor/register', 'VendorController@register');              // B
R::post('/logout', 'AuthController@logout');                           // A
R::get('/help/contact', 'TicketController@contactInfo');               // E

/* ============================ RESIDENT ============================ */

R::get('/dashboard', 'DashboardController@resident', ['role' => ['resident'], 'society' => true]);   // A
R::get('/shop', 'ShopController@index', ['role' => ['resident'], 'society' => true]);                // A
R::get('/shop/category/{id}', 'ShopController@category', ['role' => ['resident'], 'society' => true]); // A
R::get('/shop/product/{id}', 'ShopController@show', ['role' => ['resident'], 'society' => true]);    // A
R::post('/cart/add', 'CartController@add', ['role' => ['resident'], 'society' => true]);             // A
R::post('/cart/update', 'CartController@update', ['role' => ['resident'], 'society' => true]);       // A
R::post('/cart/remove', 'CartController@remove', ['role' => ['resident'], 'society' => true]);       // A
R::get('/cart', 'CartController@index', ['role' => ['resident'], 'society' => true]);                // A
R::get('/checkout', 'CartController@checkout', ['role' => ['resident'], 'society' => true]);         // A
R::post('/checkout', 'CartController@placeOrder', ['role' => ['resident'], 'society' => true]);      // A
R::get('/orders', 'OrderController@index', ['role' => ['resident'], 'society' => true]);             // A
R::get('/orders/{id}', 'OrderController@show', ['role' => ['resident'], 'society' => true]);         // A
R::post('/orders/{id}/cancel', 'OrderController@cancel', ['role' => ['resident'], 'society' => true]); // A
R::get('/orders/{id}/invoice', 'OrderController@invoice', ['role' => ['resident'], 'society' => true]); // A
R::post('/orders/{id}/repeat', 'OrderController@repeat', ['role' => ['resident'], 'society' => true]); // A
R::get('/orders/{id}/pay', 'OrderController@payPage', ['role' => ['resident'], 'society' => true]);  // A
R::post('/orders/{id}/pay', 'OrderController@submitPayment', ['role' => ['resident'], 'society' => true]); // A
R::get('/favourites', 'ShopController@favourites', ['role' => ['resident'], 'society' => true]);     // A
R::post('/favourites/toggle', 'ShopController@toggleFavourite', ['role' => ['resident'], 'society' => true]); // A
R::get('/lists', 'ShopController@lists', ['role' => ['resident'], 'society' => true]);               // A
R::post('/lists', 'ShopController@saveList', ['role' => ['resident'], 'society' => true]);           // A
R::post('/lists/{id}/delete', 'ShopController@deleteList', ['role' => ['resident'], 'society' => true]); // A
R::post('/lists/{id}/items', 'ShopController@addListItem', ['role' => ['resident'], 'society' => true]); // A
R::post('/lists/{id}/to-cart', 'ShopController@listToCart', ['role' => ['resident'], 'society' => true]); // A
R::get('/reminders', 'ShopController@reminders', ['role' => ['resident'], 'society' => true]);       // A
R::post('/reminders', 'ShopController@saveReminder', ['role' => ['resident'], 'society' => true]);   // A
R::post('/reminders/{id}/delete', 'ShopController@deleteReminder', ['role' => ['resident'], 'society' => true]); // A
R::get('/profile', 'ProfileController@show', ['auth' => true]);                                      // A
R::post('/profile', 'ProfileController@update', ['auth' => true]);                                   // A
R::get('/profile/addresses', 'ProfileController@addresses', ['role' => ['resident'], 'society' => true]); // A
R::post('/profile/addresses', 'ProfileController@saveAddress', ['role' => ['resident'], 'society' => true]); // A
R::post('/addresses/{id}/delete', 'ProfileController@deleteAddress', ['role' => ['resident'], 'society' => true]); // A

/* -------- Bulk campaigns + demand (B) -------- */
R::get('/campaigns', 'CampaignController@index', ['role' => ['resident'], 'society' => true]);       // B
R::get('/campaigns/{id}', 'CampaignController@show', ['role' => ['resident'], 'society' => true]);   // B
R::post('/campaigns/{id}/interest', 'CampaignController@interest', ['role' => ['resident'], 'society' => true]); // B
R::post('/campaigns/{id}/confirm', 'CampaignController@confirm', ['role' => ['resident'], 'society' => true]);   // B
R::post('/campaigns/{id}/cancel-interest', 'CampaignController@cancelInterest', ['role' => ['resident'], 'society' => true]); // B
R::get('/demand', 'DemandController@index', ['role' => ['resident'], 'society' => true]);            // B
R::post('/demand', 'DemandController@create', ['role' => ['resident'], 'society' => true]);          // B
R::post('/demand/{id}/vote', 'DemandController@vote', ['role' => ['resident'], 'society' => true]);  // B

/* -------- Services + bookings (C) -------- */
R::get('/services', 'ServiceController@index', ['role' => ['resident'], 'society' => true]);         // C
R::get('/services/{id}', 'ServiceController@show', ['role' => ['resident'], 'society' => true]);     // C
R::post('/services/{id}/book', 'BookingController@book', ['role' => ['resident'], 'society' => true]); // C
R::get('/service-campaigns', 'ServiceController@campaigns', ['role' => ['resident'], 'society' => true]); // C
R::get('/service-campaigns/{id}', 'ServiceController@campaignShow', ['role' => ['resident'], 'society' => true]); // C
R::post('/service-campaigns/{id}/interested', 'ServiceController@interested', ['role' => ['resident'], 'society' => true]); // C
R::post('/service-campaigns/{id}/confirm', 'ServiceController@confirmBooking', ['role' => ['resident'], 'society' => true]); // C
R::get('/bookings', 'BookingController@index', ['role' => ['resident'], 'society' => true]);         // C
R::post('/bookings/{id}/cancel', 'BookingController@cancel', ['role' => ['resident'], 'society' => true]); // C
R::post('/bookings/{id}/reschedule', 'BookingController@reschedule', ['role' => ['resident'], 'society' => true]); // C
R::post('/bookings/{id}/pay', 'BookingController@submitPayment', ['role' => ['resident'], 'society' => true]); // C

/* ============================ SHARED: notifications (E) ============================ */

R::get('/notifications', 'NotificationController@index', ['auth' => true]);                          // E
R::post('/notifications/{id}/read', 'NotificationController@read', ['auth' => true]);                // E
R::post('/notifications/read-all', 'NotificationController@readAll', ['auth' => true]);              // E

/* ============================ SOCIETY ADMIN ============================ */

R::get('/admin/dashboard', 'SocietyAdminController@dashboard', ['role' => ['society_admin']]);       // D
R::get('/admin/members', 'SocietyAdminController@members', ['role' => ['society_admin']]);           // D
R::post('/admin/memberships/{id}/approve', 'SocietyAdminController@approveMember', ['role' => ['society_admin']]);   // D
R::post('/admin/memberships/{id}/reject', 'SocietyAdminController@rejectMember', ['role' => ['society_admin']]);     // D
R::post('/admin/memberships/{id}/remove', 'SocietyAdminController@removeMember', ['role' => ['society_admin']]);     // D
R::post('/admin/residents', 'SocietyAdminController@addResident', ['role' => ['society_admin']]);    // D
R::get('/admin/flats', 'SocietyAdminController@flats', ['role' => ['society_admin']]);               // D
R::post('/admin/flats', 'SocietyAdminController@saveFlat', ['role' => ['society_admin']]);           // D
R::post('/admin/flats/{id}/delete', 'SocietyAdminController@deleteFlat', ['role' => ['society_admin']]); // D
R::get('/admin/orders', 'SocietyAdminController@orders', ['role' => ['society_admin']]);             // D
R::get('/admin/orders/{id}', 'SocietyAdminController@orderDetail', ['role' => ['society_admin']]);   // D
R::post('/admin/orders/{id}/status', 'SocietyAdminController@updateOrderStatus', ['role' => ['society_admin']]); // D
R::get('/admin/deliveries', 'SocietyAdminController@deliveries', ['role' => ['society_admin']]);     // D
R::post('/admin/deliveries/assign', 'SocietyAdminController@assignDelivery', ['role' => ['society_admin']]); // D
R::get('/admin/payments', 'SocietyAdminController@payments', ['role' => ['society_admin']]);         // D
R::post('/admin/payments/{id}/verify', 'SocietyAdminController@verifyPayment', ['role' => ['society_admin']]); // D
R::post('/admin/payments/{id}/reject', 'SocietyAdminController@rejectPayment', ['role' => ['society_admin']]); // D

/* -------- Society admin: campaigns & procurement (B) -------- */
R::get('/admin/campaigns', 'Admin\SocietyCampaignController@index', ['role' => ['society_admin']]);       // B
R::post('/admin/campaigns', 'Admin\SocietyCampaignController@create', ['role' => ['society_admin']]); // B
R::post('/admin/campaigns/{id}/close', 'Admin\SocietyCampaignController@close', ['role' => ['society_admin']]);   // B
R::post('/admin/campaigns/{id}/extend', 'Admin\SocietyCampaignController@extend', ['role' => ['society_admin']]); // B
R::post('/admin/campaigns/{id}/cancel', 'Admin\SocietyCampaignController@cancel', ['role' => ['society_admin']]); // B
R::get('/admin/campaigns/{id}', 'Admin\SocietyCampaignController@detail', ['role' => ['society_admin']]);         // B
R::post('/admin/campaigns/{id}/select-quotation', 'Admin\SocietyCampaignController@selectQuotation', ['role' => ['society_admin']]); // B
R::post('/admin/campaigns/{id}/po', 'Admin\SocietyCampaignController@createPO', ['role' => ['society_admin']]);           // B
R::post('/admin/campaigns/{id}/prices', 'Admin\SocietyCampaignController@setPrices', ['role' => ['society_admin']]);      // B
R::post('/admin/campaigns/{id}/refund', 'Admin\SocietyCampaignController@refund', ['role' => ['society_admin']]); // B
R::get('/admin/demand', 'Admin\SocietyCampaignController@demand', ['role' => ['society_admin']]);             // B
R::post('/admin/demand/{id}/validate', 'Admin\SocietyCampaignController@validateDemand', ['role' => ['society_admin']]);  // B
R::post('/admin/demand/{id}/convert', 'Admin\SocietyCampaignController@convertDemand', ['role' => ['society_admin']]);    // B
R::get('/admin/inventory', 'Admin\SocietyCampaignController@inventory', ['role' => ['society_admin']]);       // B
R::post('/admin/inventory/distribute', 'Admin\SocietyCampaignController@distribute', ['role' => ['society_admin']]); // B

/* -------- Society admin: services & bookings (C) -------- */
R::get('/admin/service-campaigns', 'Admin\\SocietyServiceController@campaigns', ['role' => ['society_admin']]);    // C
R::post('/admin/service-campaigns', 'Admin\\SocietyServiceController@create', ['role' => ['society_admin']]); // C
R::post('/admin/service-campaigns/{id}/finalize', 'Admin\\SocietyServiceController@finalize', ['role' => ['society_admin']]); // C
R::post('/admin/service-campaigns/{id}/close', 'Admin\\SocietyServiceController@close', ['role' => ['society_admin']]);       // C
R::post('/admin/service-campaigns/{id}/cancel', 'Admin\\SocietyServiceController@cancel', ['role' => ['society_admin']]);     // C
R::get('/admin/bookings', 'Admin\\SocietyServiceController@bookings', ['role' => ['society_admin']]);         // C
R::post('/admin/bookings/{id}/assign', 'Admin\\SocietyServiceController@assign', ['role' => ['society_admin']]);   // C
R::post('/admin/bookings/{id}/status', 'Admin\\SocietyServiceController@updateStatus', ['role' => ['society_admin']]); // C

/* -------- Society admin: notices/tickets/reviews (E) -------- */
R::get('/admin/notices', 'NoticeController@manage', ['role' => ['society_admin', 'super_admin']]);   // E
R::post('/admin/notices', 'NoticeController@store', ['role' => ['society_admin', 'super_admin']]);   // E
R::post('/admin/notices/{id}/pin', 'NoticeController@togglePin', ['role' => ['society_admin', 'super_admin']]); // E
R::post('/admin/notices/{id}/delete', 'NoticeController@delete', ['role' => ['society_admin', 'super_admin']]); // E
R::get('/admin/tickets', 'TicketController@adminIndex', ['role' => ['society_admin', 'super_admin']]); // E
R::get('/admin/tickets/{id}', 'TicketController@adminShow', ['role' => ['society_admin', 'super_admin']]); // E
R::post('/admin/tickets/{id}/reply', 'TicketController@reply', ['role' => ['society_admin', 'super_admin']]);  // E
R::post('/admin/tickets/{id}/status', 'TicketController@setStatus', ['role' => ['society_admin', 'super_admin']]); // E
R::post('/admin/tickets/{id}/assign', 'TicketController@assign', ['role' => ['society_admin', 'super_admin']]);    // E
R::get('/admin/reviews', 'ReviewController@moderate', ['role' => ['society_admin', 'super_admin']]); // E
R::post('/admin/reviews/{id}/status', 'ReviewController@setStatus', ['role' => ['society_admin', 'super_admin']]); // E
R::post('/admin/reviews/{id}/reply', 'ReviewController@reply', ['role' => ['society_admin', 'super_admin']]);      // E

/* -------- Society admin: coupons/reports/settings (D) -------- */
R::get('/admin/coupons', 'SocietyAdminController@coupons', ['role' => ['society_admin']]);           // D
R::post('/admin/coupons', 'SocietyAdminController@saveCoupon', ['role' => ['society_admin']]);       // D
R::post('/admin/coupons/{id}/toggle', 'SocietyAdminController@toggleCoupon', ['role' => ['society_admin']]); // D
R::get('/admin/reports', 'SocietyAdminController@reports', ['role' => ['society_admin']]);           // D
R::get('/admin/reports/export', 'SocietyAdminController@exportCsv', ['role' => ['society_admin']]);  // D
R::get('/admin/settings', 'SocietyAdminController@settings', ['role' => ['society_admin']]);         // D
R::post('/admin/settings', 'SocietyAdminController@saveSettings', ['role' => ['society_admin']]);    // D

/* ============================ SUPER ADMIN ============================ */

R::get('/super/dashboard', 'SuperAdminController@dashboard', ['role' => ['super_admin']]);           // D
R::get('/super/societies', 'SuperAdminController@societies', ['role' => ['super_admin']]);           // D
R::post('/super/societies', 'SuperAdminController@saveSociety', ['role' => ['super_admin']]);        // D
R::post('/super/societies/{id}/status', 'SuperAdminController@societyStatus', ['role' => ['super_admin']]); // D
R::get('/super/users', 'SuperAdminController@users', ['role' => ['super_admin']]);                   // D
R::post('/super/users', 'SuperAdminController@saveUser', ['role' => ['super_admin']]);               // D
R::post('/super/users/{id}/status', 'SuperAdminController@userStatus', ['role' => ['super_admin']]); // D
R::post('/super/users/{id}/reset-password', 'SuperAdminController@resetPassword', ['role' => ['super_admin']]); // D
R::get('/super/catalog', 'SuperAdminController@catalog', ['role' => ['super_admin']]);               // D
R::get('/super/catalog/template', 'SuperAdminController@catalogTemplate', ['role' => ['super_admin']]); // D
R::post('/super/catalog/bulk-upload', 'SuperAdminController@bulkUploadGroceries', ['role' => ['super_admin']]); // D
R::post('/super/categories', 'SuperAdminController@saveCategory', ['role' => ['super_admin']]);      // D
R::post('/super/categories/{id}/delete', 'SuperAdminController@deleteCategory', ['role' => ['super_admin']]); // D
R::post('/super/brands', 'SuperAdminController@saveBrand', ['role' => ['super_admin']]);             // D
R::post('/super/products', 'SuperAdminController@saveProduct', ['role' => ['super_admin']]);         // D
R::post('/super/products/{id}/delete', 'SuperAdminController@deleteProduct', ['role' => ['super_admin']]); // D
R::post('/super/variants', 'SuperAdminController@saveVariant', ['role' => ['super_admin']]);         // D
R::post('/super/variants/{id}/delete', 'SuperAdminController@deleteVariant', ['role' => ['super_admin']]); // D
R::post('/super/prices', 'SuperAdminController@saveSocietyPrice', ['role' => ['super_admin']]);      // D
R::get('/super/vendors', 'SuperAdminController@vendors', ['role' => ['super_admin']]);               // D
R::post('/super/vendors', 'SuperAdminController@saveVendor', ['role' => ['super_admin']]);           // D
R::post('/super/vendors/{id}/status', 'SuperAdminController@vendorStatus', ['role' => ['super_admin']]); // D
R::get('/super/purchase-orders', 'Admin\\PlatformProcurementController@purchaseOrders', ['role' => ['super_admin']]); // B
R::get('/super/purchase-orders/{id}', 'Admin\\PlatformProcurementController@poDetail', ['role' => ['super_admin']]);  // B
R::post('/super/purchase-orders/{id}/status', 'Admin\\PlatformProcurementController@poStatus', ['role' => ['super_admin']]); // B
R::post('/super/purchase-orders/{id}/receive', 'Admin\\PlatformProcurementController@poReceive', ['role' => ['super_admin']]); // B
R::get('/super/inventory', 'Admin\\PlatformProcurementController@inventory', ['role' => ['super_admin']]);           // B
R::post('/super/inventory/adjust', 'Admin\\PlatformProcurementController@inventoryAdjust', ['role' => ['super_admin']]); // B
R::get('/super/campaigns', 'Admin\\PlatformProcurementController@campaigns', ['role' => ['super_admin']]);           // B
R::post('/super/campaigns/{id}/prices', 'Admin\\PlatformProcurementController@setPrices', ['role' => ['super_admin']]); // B
R::post('/super/campaigns/{id}/cancel', 'Admin\\PlatformProcurementController@cancelCampaign', ['role' => ['super_admin']]); // B
R::get('/super/quotations', 'Admin\\PlatformProcurementController@quotations', ['role' => ['super_admin']]);         // B
R::post('/super/quotations/{id}/select', 'Admin\\PlatformProcurementController@selectQuotation', ['role' => ['super_admin']]); // B
R::post('/super/quotations/{id}/reject', 'Admin\\PlatformProcurementController@rejectQuotation', ['role' => ['super_admin']]); // B

R::get('/super/coupons', 'SuperAdminController@coupons', ['role' => ['super_admin']]);               // D
R::post('/super/coupons', 'SuperAdminController@saveCoupon', ['role' => ['super_admin']]);           // D
R::post('/super/coupons/{id}/toggle', 'SuperAdminController@toggleCoupon', ['role' => ['super_admin']]); // D
R::get('/super/subscriptions', 'SuperAdminController@subscriptions', ['role' => ['super_admin']]);   // D
R::post('/super/subscriptions', 'SuperAdminController@saveSubscription', ['role' => ['super_admin']]); // D
R::get('/super/commissions', 'SuperAdminController@commissions', ['role' => ['super_admin']]);       // D
R::post('/super/commissions', 'SuperAdminController@saveCommission', ['role' => ['super_admin']]);   // D
R::get('/super/payments', 'SuperAdminController@payments', ['role' => ['super_admin']]);             // D
R::post('/super/payments/{id}/verify', 'SuperAdminController@verifyPayment', ['role' => ['super_admin']]); // D
R::post('/super/payments/{id}/reject', 'SuperAdminController@rejectPayment', ['role' => ['super_admin']]); // D
R::get('/super/refunds', 'SuperAdminController@refunds', ['role' => ['super_admin']]);               // D
R::post('/super/refunds', 'SuperAdminController@saveRefund', ['role' => ['super_admin']]);           // D
R::get('/super/reviews', 'ReviewController@moderate', ['role' => ['super_admin']]);                  // E
R::get('/super/tickets', 'TicketController@adminIndex', ['role' => ['super_admin']]);                // E
R::get('/super/reports', 'SuperAdminController@reports', ['role' => ['super_admin']]);               // D
R::get('/super/reports/export', 'SuperAdminController@exportCsv', ['role' => ['super_admin']]);      // D
R::get('/super/audit', 'SuperAdminController@audit', ['role' => ['super_admin']]);                   // D
R::get('/super/settings', 'SuperAdminController@settings', ['role' => ['super_admin']]);             // D
R::post('/super/settings', 'SuperAdminController@saveSettings', ['role' => ['super_admin']]);        // D

/* ============================ VENDOR ============================ */

R::get('/vendor/dashboard', 'VendorController@dashboard', ['role' => ['vendor']]);                   // B
R::get('/vendor/quotations', 'VendorController@quotations', ['role' => ['vendor']]);                 // B
R::post('/vendor/quotations', 'VendorController@submitQuotation', ['role' => ['vendor']]);           // B
R::get('/vendor/products', 'VendorController@products', ['role' => ['vendor']]);                     // B
R::get('/vendor/products/template', 'VendorController@catalogTemplate', ['role' => ['vendor']]);       // B
R::post('/vendor/products/bulk-upload', 'VendorController@bulkUploadGroceries', ['role' => ['vendor']]); // B
R::post('/vendor/products/bulk-images', 'VendorController@bulkUploadImages', ['role' => ['vendor']]);  // B
R::post('/vendor/products', 'VendorController@saveProduct', ['role' => ['vendor']]);                 // B
R::post('/vendor/variants', 'VendorController@saveVariant', ['role' => ['vendor']]);                 // B
R::get('/vendor/purchase-orders', 'VendorController@purchaseOrders', ['role' => ['vendor']]);        // B
R::post('/vendor/purchase-orders/{id}/status', 'VendorController@poStatus', ['role' => ['vendor']]); // B
R::get('/vendor/bookings', 'VendorServiceController@bookings', ['role' => ['vendor']]);                     // C
R::post('/vendor/bookings/{id}/status', 'VendorServiceController@bookingStatus', ['role' => ['vendor']]);   // C
R::get('/vendor/reviews', 'VendorServiceController@reviews', ['role' => ['vendor']]);                       // C

/* ============================ DELIVERY STAFF ============================ */

R::get('/delivery/dashboard', 'DeliveryController@dashboard', ['role' => ['delivery_staff']]);       // C
R::post('/delivery/tasks/{id}/status', 'DeliveryController@updateStatus', ['role' => ['delivery_staff']]); // C
R::post('/delivery/tasks/{id}/proof', 'DeliveryController@uploadProof', ['role' => ['delivery_staff']]);   // C
R::get('/delivery/history', 'DeliveryController@history', ['role' => ['delivery_staff']]);           // C

/* ============================ COMMUNITY — RESIDENT SIDE (E) ============================ */

R::get('/notices', 'NoticeController@index', ['role' => ['resident'], 'society' => true]);           // E
R::get('/notices/{id}', 'NoticeController@show', ['role' => ['resident'], 'society' => true]);       // E
R::get('/tickets', 'TicketController@index', ['auth' => true]);                                      // E
R::get('/tickets/new', 'TicketController@create', ['auth' => true]);                                 // E
R::post('/tickets', 'TicketController@store', ['auth' => true]);                                     // E
R::get('/tickets/{id}', 'TicketController@show', ['auth' => true]);                                  // E
R::post('/tickets/{id}/reply', 'TicketController@userReply', ['auth' => true]);                      // E
R::post('/tickets/{id}/reopen', 'TicketController@reopen', ['auth' => true]);                        // E
R::post('/tickets/{id}/feedback', 'TicketController@feedback', ['auth' => true]);                    // E
R::get('/reviews/new', 'ReviewController@create', ['role' => ['resident'], 'society' => true]);      // E
R::post('/reviews', 'ReviewController@store', ['role' => ['resident'], 'society' => true]);          // E
R::post('/reviews/{id}/report', 'ReviewController@report', ['role' => ['resident'], 'society' => true]); // E
