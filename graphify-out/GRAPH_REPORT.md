# Graph Report - .  (2026-06-03)

## Corpus Check
- Corpus is ~37,889 words - fits in a single context window. You may not need a graph.

## Summary
- 1020 nodes · 1671 edges · 98 communities (76 shown, 22 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 1 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Policy Layer|Policy Layer]]
- [[_COMMUNITY_DataTables Layer|DataTables Layer]]
- [[_COMMUNITY_Fractal Transformers|Fractal Transformers]]
- [[_COMMUNITY_API Presenters|API Presenters]]
- [[_COMMUNITY_Admin Web Controllers|Admin Web Controllers]]
- [[_COMMUNITY_API REST Controllers|API REST Controllers]]
- [[_COMMUNITY_Business Logic Services|Business Logic Services]]
- [[_COMMUNITY_Form Request Validation|Form Request Validation]]
- [[_COMMUNITY_Domain Events|Domain Events]]
- [[_COMMUNITY_Mobile API Controller|Mobile API Controller]]
- [[_COMMUNITY_Event-Listener Pipeline|Event-Listener Pipeline]]
- [[_COMMUNITY_AI Chat Integration|AI Chat Integration]]
- [[_COMMUNITY_Rental Model & Traits|Rental Model & Traits]]
- [[_COMMUNITY_API Layer Transformers|API Layer Transformers]]
- [[_COMMUNITY_Availability Model|Availability Model]]
- [[_COMMUNITY_Booking State Machine|Booking State Machine]]
- [[_COMMUNITY_Notifications & Queue Jobs|Notifications & Queue Jobs]]
- [[_COMMUNITY_Stripe Connect Controller|Stripe Connect Controller]]
- [[_COMMUNITY_Admin Availabilities CRUD|Admin Availabilities CRUD]]
- [[_COMMUNITY_Admin Pricing Rules CRUD|Admin Pricing Rules CRUD]]
- [[_COMMUNITY_Admin Reviews CRUD|Admin Reviews CRUD]]
- [[_COMMUNITY_Admin Transactions CRUD|Admin Transactions CRUD]]
- [[_COMMUNITY_Admin Rentals CRUD|Admin Rentals CRUD]]
- [[_COMMUNITY_Admin Device Tokens CRUD|Admin Device Tokens CRUD]]
- [[_COMMUNITY_Module Configuration|Module Configuration]]
- [[_COMMUNITY_Database Seeders|Database Seeders]]
- [[_COMMUNITY_AI Chat Session Model|AI Chat Session Model]]
- [[_COMMUNITY_Service Providers|Service Providers]]
- [[_COMMUNITY_API Availabilities Controller|API Availabilities Controller]]
- [[_COMMUNITY_API Categories Controller|API Categories Controller]]
- [[_COMMUNITY_API Pricing Rules Controller|API Pricing Rules Controller]]
- [[_COMMUNITY_API Rentals Controller|API Rentals Controller]]
- [[_COMMUNITY_API Transactions Controller|API Transactions Controller]]
- [[_COMMUNITY_API Device Tokens Controller|API Device Tokens Controller]]
- [[_COMMUNITY_Artisan Commands|Artisan Commands]]
- [[_COMMUNITY_Database Migrations|Database Migrations]]
- [[_COMMUNITY_FCM Push Channel|FCM Push Channel]]
- [[_COMMUNITY_User Device Token Model|User Device Token Model]]
- [[_COMMUNITY_Booking Cancelled Notification|Booking Cancelled Notification]]
- [[_COMMUNITY_Booking Confirmed Notification|Booking Confirmed Notification]]
- [[_COMMUNITY_Booking Received Notification|Booking Received Notification]]
- [[_COMMUNITY_Booking Waitlisted Notification|Booking Waitlisted Notification]]
- [[_COMMUNITY_Review Reminder Notification|Review Reminder Notification]]
- [[_COMMUNITY_Waitlist Available Notification|Waitlist Available Notification]]
- [[_COMMUNITY_Review Model|Review Model]]
- [[_COMMUNITY_Waitlist Promotion Listener|Waitlist Promotion Listener]]
- [[_COMMUNITY_Transaction Model|Transaction Model]]
- [[_COMMUNITY_Main Service Provider|Main Service Provider]]
- [[_COMMUNITY_FCM Message Value Object|FCM Message Value Object]]
- [[_COMMUNITY_HasAvailability Trait|HasAvailability Trait]]
- [[_COMMUNITY_Composer & Dependencies|Composer & Dependencies]]
- [[_COMMUNITY_Module Install Provider|Module Install Provider]]
- [[_COMMUNITY_Module Uninstall Provider|Module Uninstall Provider]]
- [[_COMMUNITY_Availability Observer|Availability Observer]]
- [[_COMMUNITY_Booking Observer|Booking Observer]]
- [[_COMMUNITY_Category Observer|Category Observer]]
- [[_COMMUNITY_Pricing Rule Observer|Pricing Rule Observer]]
- [[_COMMUNITY_Rental Observer|Rental Observer]]
- [[_COMMUNITY_Review Observer|Review Observer]]
- [[_COMMUNITY_Transaction Observer|Transaction Observer]]
- [[_COMMUNITY_Device Token Observer|Device Token Observer]]
- [[_COMMUNITY_HasPricingRules Trait|HasPricingRules Trait]]
- [[_COMMUNITY_Rentyx Facade|Rentyx Facade]]
- [[_COMMUNITY_Module Update Provider|Module Update Provider]]
- [[_COMMUNITY_Rentyx Core Class|Rentyx Core Class]]
- [[_COMMUNITY_WaitlistAvailable Exception|WaitlistAvailable Exception]]
- [[_COMMUNITY_Rental Blade Views|Rental Blade Views]]

## God Nodes (most connected - your core abstractions)
1. `RentyxMobileController` - 25 edges
2. `Rental` - 24 edges
3. `Request` - 23 edges
4. `JsonResponse` - 23 edges
5. `ChatController` - 21 edges
6. `Booking` - 20 edges
7. `RentalBaseEvent` - 19 edges
8. `Notification` - 16 edges
9. `ChatSession` - 13 edges
10. `Booking` - 12 edges

## Surprising Connections (you probably didn't know these)
- `BookingCancelledNotification` --inherits--> `Notification`  [EXTRACTED]
  Corals/modules/Rentyx/Notifications/BookingCancelledNotification.php → Corals/modules/Rentyx/Channels/FcmChannel.php
- `BookingConfirmedNotification` --inherits--> `Notification`  [EXTRACTED]
  Corals/modules/Rentyx/Notifications/BookingConfirmedNotification.php → Corals/modules/Rentyx/Channels/FcmChannel.php
- `BookingReceivedNotification` --inherits--> `Notification`  [EXTRACTED]
  Corals/modules/Rentyx/Notifications/BookingReceivedNotification.php → Corals/modules/Rentyx/Channels/FcmChannel.php
- `BookingWaitlistedNotification` --inherits--> `Notification`  [EXTRACTED]
  Corals/modules/Rentyx/Notifications/BookingWaitlistedNotification.php → Corals/modules/Rentyx/Channels/FcmChannel.php
- `DisputeOpenedNotification` --inherits--> `Notification`  [EXTRACTED]
  Corals/modules/Rentyx/Notifications/DisputeOpenedNotification.php → Corals/modules/Rentyx/Channels/FcmChannel.php

## Import Cycles
- None detected.

## Communities (98 total, 22 thin omitted)

### Community 0 - "Policy Layer"
Cohesion: 0.06
Nodes (25): BasePolicy, Availability, User, Booking, User, Category, User, PricingRule (+17 more)

### Community 1 - "DataTables Layer"
Cohesion: 0.05
Nodes (17): BaseDataTable, Availability, Booking, Category, PricingRule, Rental, Review, Transaction (+9 more)

### Community 2 - "Fractal Transformers"
Cohesion: 0.07
Nodes (17): BaseTransformer, Availability, Booking, Category, PricingRule, Rental, Review, Transaction (+9 more)

### Community 3 - "API Presenters"
Cohesion: 0.07
Nodes (14): AvailabilityPresenter, BookingPresenter, CategoryPresenter, RentalPresenter, ReviewPresenter, FractalPresenter, AvailabilityPresenter, BookingPresenter (+6 more)

### Community 4 - "Admin Web Controllers"
Cohesion: 0.10
Nodes (13): BaseController, BookingsController, CategoriesController, ReportsController, Booking, BookingRequest, BookingsDataTable, BookingService (+5 more)

### Community 5 - "API REST Controllers"
Cohesion: 0.11
Nodes (17): BookingsController, PaymentController, ReviewsController, APIBaseController, Charge, Booking, BookingRequest, BookingsDataTable (+9 more)

### Community 6 - "Business Logic Services"
Cohesion: 0.14
Nodes (15): BaseServiceClass, Booking, Carbon, Rental, Rental, Booking, Review, AvailabilityService (+7 more)

### Community 7 - "Form Request Validation"
Cohesion: 0.08
Nodes (9): BaseRequest, AvailabilityRequest, BookingRequest, CategoryRequest, PricingRuleRequest, RentalRequest, ReviewRequest, TransactionRequest (+1 more)

### Community 8 - "Domain Events"
Cohesion: 0.08
Nodes (13): Dispatchable, BookingActivated, BookingCancelled, BookingCompleted, BookingConfirmed, BookingCreated, BookingDisputed, BookingWaitlistAvailable (+5 more)

### Community 9 - "Mobile API Controller"
Cohesion: 0.22
Nodes (5): RentyxMobileController, Booking, JsonResponse, Rental, Request

### Community 10 - "Event-Listener Pipeline"
Cohesion: 0.12
Nodes (14): BookingCompleted, BookingConfirmed, BookingCreated, BookingDisputed, BookingWaitlisted, BookingCancelled, NotifyAdminOfDispute, SendBookingCancelledNotification (+6 more)

### Community 11 - "AI Chat Integration"
Cohesion: 0.20
Nodes (4): ChatController, ChatSession, JsonResponse, Request

### Community 12 - "Rental Model & Traits"
Cohesion: 0.11
Nodes (7): HasAvailability, HasMedia, HasPricingRules, HasSlug, InteractsWithMedia, Rental, SlugOptions

### Community 13 - "API Layer Transformers"
Cohesion: 0.14
Nodes (11): AvailabilityTransformer, BookingTransformer, CategoryTransformer, RentalTransformer, ReviewTransformer, APIBaseTransformer, Availability, Booking (+3 more)

### Community 14 - "Availability Model"
Cohesion: 0.15
Nodes (7): BaseModel, LogsActivity, Availability, Category, ChatMessage, PricingRule, PresentableTrait

### Community 16 - "Notifications & Queue Jobs"
Cohesion: 0.15
Nodes (7): FcmMessage, MailMessage, InteractsWithQueue, ProcessOwnerPayout, DisputeOpenedNotification, Queueable, Throwable

### Community 17 - "Stripe Connect Controller"
Cohesion: 0.30
Nodes (6): Account, StripeConnectController, Application, Booking, JsonResponse, Request

### Community 18 - "Admin Availabilities CRUD"
Cohesion: 0.29
Nodes (5): AvailabilitiesController, AvailabilitiesDataTable, Availability, AvailabilityRequest, AvailabilityService

### Community 19 - "Admin Pricing Rules CRUD"
Cohesion: 0.29
Nodes (5): PricingRulesController, PricingRule, PricingRuleRequest, PricingRulesDataTable, PricingRuleService

### Community 20 - "Admin Reviews CRUD"
Cohesion: 0.29
Nodes (5): ReviewsController, Review, ReviewRequest, ReviewsDataTable, ReviewService

### Community 21 - "Admin Transactions CRUD"
Cohesion: 0.29
Nodes (5): TransactionsController, Transaction, TransactionRequest, TransactionsDataTable, TransactionService

### Community 22 - "Admin Rentals CRUD"
Cohesion: 0.30
Nodes (5): RentalsController, Rental, RentalRequest, RentalsDataTable, RentalService

### Community 23 - "Admin Device Tokens CRUD"
Cohesion: 0.30
Nodes (5): UserDeviceTokensController, UserDeviceToken, UserDeviceTokenRequest, UserDeviceTokensDataTable, UserDeviceTokenService

### Community 24 - "Module Configuration"
Cohesion: 0.14
Nodes (13): author, autoload, code, description, folder, icon, load_order, name (+5 more)

### Community 25 - "Database Seeders"
Cohesion: 0.19
Nodes (5): Seeder, RentyxDatabaseSeeder, RentyxMenuDatabaseSeeder, RentyxPermissionsDatabaseSeeder, RentyxSettingsDatabaseSeeder

### Community 27 - "Service Providers"
Cohesion: 0.19
Nodes (4): RentyxAuthServiceProvider, RentyxObserverServiceProvider, RentyxRouteServiceProvider, ServiceProvider

### Community 28 - "API Availabilities Controller"
Cohesion: 0.33
Nodes (5): AvailabilitiesController, AvailabilitiesDataTable, Availability, AvailabilityRequest, AvailabilityService

### Community 29 - "API Categories Controller"
Cohesion: 0.33
Nodes (5): CategoriesController, CategoriesDataTable, Category, CategoryRequest, CategoryService

### Community 30 - "API Pricing Rules Controller"
Cohesion: 0.33
Nodes (5): PricingRulesController, PricingRule, PricingRuleRequest, PricingRulesDataTable, PricingRuleService

### Community 31 - "API Rentals Controller"
Cohesion: 0.33
Nodes (5): RentalsController, Rental, RentalRequest, RentalsDataTable, RentalService

### Community 32 - "API Transactions Controller"
Cohesion: 0.33
Nodes (5): TransactionsController, Transaction, TransactionRequest, TransactionsDataTable, TransactionService

### Community 33 - "API Device Tokens Controller"
Cohesion: 0.33
Nodes (5): UserDeviceTokensController, UserDeviceToken, UserDeviceTokenRequest, UserDeviceTokensDataTable, UserDeviceTokenService

### Community 34 - "Artisan Commands"
Cohesion: 0.24
Nodes (4): Command, ExpirePendingBookings, PruneStaleTokens, SendReviewReminders

### Community 35 - "Database Migrations"
Cohesion: 0.27
Nodes (4): Blueprint, Migration, AddWaitlistFieldsToRentyxBookings, RentyxTables

### Community 36 - "FCM Push Channel"
Cohesion: 0.29
Nodes (4): FcmChannel, FcmMessage, Notification, base64url_encode()

### Community 38 - "Booking Cancelled Notification"
Cohesion: 0.28
Nodes (3): FcmMessage, MailMessage, BookingCancelledNotification

### Community 39 - "Booking Confirmed Notification"
Cohesion: 0.28
Nodes (3): FcmMessage, MailMessage, BookingConfirmedNotification

### Community 40 - "Booking Received Notification"
Cohesion: 0.28
Nodes (3): FcmMessage, MailMessage, BookingReceivedNotification

### Community 41 - "Booking Waitlisted Notification"
Cohesion: 0.28
Nodes (3): FcmMessage, MailMessage, BookingWaitlistedNotification

### Community 42 - "Review Reminder Notification"
Cohesion: 0.28
Nodes (3): FcmMessage, MailMessage, ReviewReminderNotification

### Community 43 - "Waitlist Available Notification"
Cohesion: 0.28
Nodes (3): FcmMessage, MailMessage, WaitlistAvailableNotification

### Community 45 - "Waitlist Promotion Listener"
Cohesion: 0.32
Nodes (4): BookingWaitlistAvailable, BookingCancelled, PromoteWaitlistOnCancellation, SendWaitlistAvailableNotification

### Community 49 - "HasAvailability Trait"
Cohesion: 0.48
Nodes (5): Availability, Carbon, blockDates(), getUnavailableDatesForMonth(), isAvailableForPeriod()

### Community 50 - "Composer & Dependencies"
Cohesion: 0.40
Nodes (4): autoload, files, require, anthropic-ai/sdk

### Community 61 - "HasPricingRules Trait"
Cohesion: 1.00
Nodes (3): Carbon, calculateDailyBreakdown(), getApplicableRate()

## Knowledge Gaps
- **21 isolated node(s):** `FcmMessage`, `Charge`, `Application`, `Throwable`, `self` (+16 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **22 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `ProcessOwnerPayout` connect `Notifications & Queue Jobs` to `Domain Events`, `Event-Listener Pipeline`?**
  _High betweenness centrality (0.016) - this node is a cross-community bridge._
- **Why does `BookingCreated` connect `Event-Listener Pipeline` to `Business Logic Services`, `Booking State Machine`?**
  _High betweenness centrality (0.015) - this node is a cross-community bridge._
- **What connects `FcmMessage`, `Charge`, `Application` to the rest of the system?**
  _21 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Policy Layer` be split into smaller, more focused modules?**
  _Cohesion score 0.057692307692307696 - nodes in this community are weakly interconnected._
- **Should `DataTables Layer` be split into smaller, more focused modules?**
  _Cohesion score 0.05442176870748299 - nodes in this community are weakly interconnected._
- **Should `Fractal Transformers` be split into smaller, more focused modules?**
  _Cohesion score 0.06829268292682927 - nodes in this community are weakly interconnected._
- **Should `API Presenters` be split into smaller, more focused modules?**
  _Cohesion score 0.06666666666666667 - nodes in this community are weakly interconnected._