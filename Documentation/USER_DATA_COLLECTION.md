# User Data Collection and Usage Tracking

## 1. Click Tracking (UI Events)
- User interface interactions, specifically menu clicks, can be captured.
- This tracking is handled by the `library/ajax/track_events.php` script.
- When a tracked menu item is clicked, the following data points are collected:
    - `eventType`: The type of event (e.g., 'click').
    - `eventLabel`: A label describing the clicked element (e.g., the menu item's text or ID).
    - `eventUrl`: The URL associated with the click, which may be normalized (query parameters and webroot path removed).
    - `eventTarget`: The target of the event, if applicable.
- This collected click event data is stored in a database table named `track_events`. If an identical event (based on type, label, URL, and target) is triggered multiple times, a counter (`label_count`) for that event is incremented, and the timestamp of the last occurrence (`last_event`) is updated.

## 2. API Request Tracking
- In addition to UI clicks, requests made to the system's Application Programming Interfaces (APIs) can also be tracked.
- This is facilitated by the `TelemetryService::trackApiRequestEvent()` method found in `src/Telemetry/TelemetryService.php`.
- When an API request is configured for tracking, it is logged in a manner similar to click events, utilizing the same `track_events` database table and structure (eventType, eventLabel, eventUrl, eventTarget).
- This tracking is only active if telemetry is enabled in the system configuration.

## 3. Periodic Usage Data Reporting
The system includes a mechanism to periodically report aggregated usage data to an external server. This process is managed as follows:

- **Trigger**: A background task, defined in `src/Telemetry/BackgroundTaskManager.php` and stored in the `background_services` table (typically named 'Telemetry_Task'), is scheduled to run approximately every 33 days. This task initiates the data reporting process by calling `TelemetryService::reportUsageData()` (located in `src/Telemetry/TelemetryService.php`).
- **Data Aggregation**: When triggered, the system collects the following information:
    - All records from the local `track_events` database table (which includes the click and API tracking data).
    - A **Unique Site Installation UUID**, generated for each OpenEMR instance.
    - **Server Geolocation**: The system attempts to determine the server's geographical location (country, region, city, latitude, longitude) using its public IP address. This is done by the `GeoTelemetry.php` class, which queries external services like `ipapi.co` and `geoplugin.net`.
    - **Server Environment Details**:
        - Operating System (e.g., Linux, Windows).
        - PHP version.
        - OpenEMR software version.
        - Docker tag (if the instance is running in a Docker container, this indicates the distribution).
    - **System Settings**:
        - The server's configured timezone.
        - The system's default locale.
        - Status of certain features, such as whether the patient portal (`portal_onsite_two_enable`) is enabled.
- **Data Transmission**:
    - The aggregated data is compiled into a JSON payload.
    - This payload is then sent via an HTTPS POST request to `https://reg.open-emr.org/api/usage`.
- **Post-Transmission**:
    - If the data is successfully reported to the external server (indicated by a successful HTTP status code like 200, 201, or 204), the local `track_events` table is truncated (all data is cleared). This is to ensure that the same event data is not sent multiple times.
- **Condition**: This entire reporting process only occurs if telemetry is enabled in the system.

## 4. Enabling and Disabling Telemetry
The collection and reporting of telemetry data can be controlled through system settings:

- **Primary Control (`product_registration` table)**:
    - The main switch for telemetry is managed within the `product_registration` database table.
    - A column named `telemetry_disabled` dictates the status:
        - If a record exists with `telemetry_disabled = 0`, telemetry is considered **enabled**.
        - If `telemetry_disabled` is any other value (e.g., `1`) or if the relevant record is not found or configured in this way, telemetry is considered **disabled**.
    - The `TelemetryService::isTelemetryEnabled()` method checks this setting to determine if data should be collected or reported.

- **Background Task Control**:
    - The periodic reporting of usage data is handled by a background task (typically 'Telemetry_Task').
    - The `src/Telemetry/BackgroundTaskManager.php` class provides functions to manage this task:
        - `enableTelemetryTask()`: Activates the background task, allowing it to run at scheduled intervals.
        - `disableTelemetryTask()`: Deactivates the background task, preventing automatic data reporting.
    - Disabling the task in `background_services` table (e.g., by setting its `active` status to `0`) will prevent the automatic periodic sending of usage data, even if the primary telemetry setting in `product_registration` is enabled. However, click and API event tracking might still occur and be stored locally if the primary setting allows it.

## 5. Server Geolocation
As part of the periodic usage data reporting, the system attempts to determine the geographical location of the server hosting the OpenEMR instance.

- **Purpose**: Geolocation data (country, region, city, latitude, longitude) is collected to understand the general distribution of OpenEMR installations.
- **Mechanism**:
    - This is handled by the `src/Telemetry/GeoTelemetry.php` class.
    - The class first attempts to get the server's public IP address by contacting `https://api.ipify.org`.
    - If the public IP is successfully retrieved, `GeoTelemetry.php` then queries the following third-party services (in order) to get location details:
        1. `https://ipapi.co/{IP_ADDRESS}/json/`
        2. `http://www.geoplugin.net/json.gp?ip={IP_ADDRESS}` (as a fallback)
    - The collected geolocation data is then included in the periodic usage report sent to `https://reg.open-emr.org`.
- **IP Address Handling**:
    - The server's actual public IP address is used to query the geolocation services.
    - The `GeoTelemetry.php` class also contains a method `anonymizeIp()` which can hash an IP address using SHA-256. However, based on the current implementation of `getServerGeoData()` and its usage within `TelemetryService.php`, this anonymization function is **not** applied to the server's IP address before it's used for geolocation lookups or before the geolocation data (which doesn't explicitly include the IP) is sent in the telemetry report. The raw IP is used for the lookup, and the resulting location data is reported.

## 6. What is NOT Tracked by This Telemetry System
It's important to clarify what this specific telemetry system (as described in `src/Telemetry/` and `library/ajax/track_events.php`) does **not** collect:

- **Patient-Specific Data**:
    - The system does not transmit any Electronic Health Records (EHR), Protected Health Information (PHI), or Personally Identifiable Information (PII) of patients.
    - Data entered into patient forms, clinical notes, medical history, billing details (other than aggregated financial settings if ever included), or any other patient-specific fields are not part of this telemetry data.
- **Detailed User Activity within Forms**:
    - While API calls (which might be triggered by form submissions) can be logged, the specific values entered into individual form fields by users are not tracked by this telemetry mechanism.
- **User Credentials**:
    - Usernames, passwords, or any other user-specific authentication details are not collected. The tracking is generally anonymized at the user level, focusing on site-level aggregation. The only identifier is the `site_uuid` for the installation.
- **General Mouse Movements or Heatmaps**:
    - The described telemetry system focuses on specific events like menu clicks and API calls. It does not implement broad tracking of all mouse movements, hovers, or generate heatmaps of user activity across the entire application. (Note: Other parts of the system, if any, outside of the examined telemetry module might implement different specific tracking for particular features, but that is not covered by this document which focuses on the telemetry infrastructure in `src/Telemetry/`).
- **Application Errors or Detailed Debug Logs**:
    - While the `TelemetryService` uses a logger for its own operations, this telemetry system is not designed to transmit application error details or extensive debug logs to the external server.
