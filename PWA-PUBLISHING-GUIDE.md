# YourJannah PWA Publishing Guide

## Publishing to Google Play Store and Apple App Store

**App**: YourJannah -- Mosque Community Platform
**Stack**: WordPress PWA with Web Push Notifications
**Date**: April 2026

---

## Table of Contents

1. [Google Play Store (TWA)](#1-google-play-store-twa)
2. [Apple App Store (Native Wrapper)](#2-apple-app-store-native-wrapper)
3. [PWABuilder](#3-pwabuilder)
4. [Capacitor (Ionic)](#4-capacitor-ionic)
5. [Requirements Checklist](#5-requirements-checklist)
6. [Recommended Approach](#6-recommended-approach)
7. [Cost Summary](#7-cost-summary)
8. [Timeline](#8-timeline)

---

## 1. Google Play Store (TWA)

### What is a TWA?

A Trusted Web Activity (TWA) uses Chrome's rendering engine to display your PWA
in a full-screen Android app with no browser UI. Google officially supports this
approach for putting PWAs on the Play Store.

### Requirements

- **Web App Manifest** (`manifest.json`) with name, icons, start_url, display,
  theme_color, background_color
- **Service Worker** registered and functional (offline support)
- **HTTPS** -- mandatory, no exceptions
- **Digital Asset Links** (`assetlinks.json`) for domain ownership verification
- **Lighthouse PWA score** of 80+ (Google recommends this for TWA)

### Setting Up assetlinks.json

This file proves you own the domain that the TWA loads. It must be served at:

```
https://yourjannah.com/.well-known/assetlinks.json
```

Content:

```json
[{
  "relation": ["delegate_permission/common.handle_all_urls"],
  "target": {
    "namespace": "android_app",
    "package_name": "com.yourjannah.app",
    "sha256_cert_fingerprints": [
      "YOUR_SIGNING_KEY_SHA256_FINGERPRINT"
    ]
  }
}]
```

Requirements for the file:
- Served over HTTPS, no redirects
- Content-Type: application/json
- Publicly accessible (not behind auth or VPN)
- Each domain/subdomain needs its own file

Validate with:
```
https://digitalassetlinks.googleapis.com/v1/statements:list?source.web.site=https://yourjannah.com&relation=delegate_permission/common.handle_all_urls
```

### Option A: Bubblewrap CLI (Recommended for Android)

Bubblewrap is Google Chrome Labs' official CLI tool for generating Android
packages from PWAs.

**Step-by-step process:**

```bash
# 1. Install Bubblewrap globally
npm install -g @bubblewrap/cli

# 2. Create a SEPARATE directory (do NOT run in your web project root)
mkdir yourjannah-android
cd yourjannah-android

# 3. Initialize from your PWA's manifest URL
bubblewrap init --manifest https://yourjannah.com/manifest.json

# Bubblewrap will:
# - Download and configure JDK and Android SDK automatically
# - Read your manifest and pre-fill app name, icons, colors
# - Ask for package name (use: com.yourjannah.app)
# - Ask for signing key path (creates one if you don't have one)
# - Generate the Android project files

# 4. Build the packages
bubblewrap build

# Output files:
# - app-release-bundle.aab  --> Upload this to Google Play Console
# - app-release-signed.apk  --> For direct device testing

# 5. Test on a connected device
bubblewrap install
```

**Signing key**: Google Play requires apps to be digitally signed. Bubblewrap
will prompt you to create or provide a keystore. KEEP THIS KEYSTORE SAFE -- you
need it for every future update. Consider using Google Play App Signing (lets
Google manage the upload key).

### Option B: PWABuilder (GUI Alternative)

See Section 3 below. Uses Bubblewrap under the hood but provides a web
interface.

### Push Notifications in TWA

TWA supports **notification delegation** -- your existing Web Push notifications
work inside the TWA without any native Android code. When a web push arrives:

- Chrome handles the push via the service worker (same as browser)
- The notification appears as a native Android notification
- Clicking the notification brings the TWA app to the foreground via
  FocusActivity

Bubblewrap enables notification delegation by default. Your existing Web Push
setup (VAPID keys, service worker push handler) works as-is.

### Google Play Developer Account

- **Cost**: $25 USD one-time registration fee (non-refundable)
- **No annual fee**
- **Activation**: 24-48 hours for personal accounts; several days for
  organization accounts
- **New requirement (since late 2023)**: Personal accounts must complete testing
  requirements before publishing. You need to verify device access via the Play
  Console mobile app
- **Organization accounts** require a D-U-N-S number (like Apple)
- **URL**: https://play.google.com/console/signup

---

## 2. Apple App Store (Native Wrapper)

### The Core Challenge

Apple does NOT have a TWA equivalent. There is no official, supported way to put
a PWA on the App Store. You must create a native iOS app that uses WKWebView to
load your PWA.

**Guideline 4.2 -- Minimum Functionality**: Apple rejects apps that are
"repackaged websites" or "web clippings." The review team looks for apps that
are "not sufficiently different from a mobile web browsing experience." This is
the single biggest hurdle.

### What Gets Rejected

- A bare WKWebView pointing at a URL with no native features
- Apps that look and feel identical to the mobile website
- Apps with no offline functionality
- Apps that don't use any native iOS capabilities

### What Passes Review

Apps that genuinely add native functionality beyond the web:

1. **Native push notifications** via APNs (not web push)
2. **Native navigation** (tab bars, navigation controllers)
3. **Offline content** and caching
4. **Native UI elements** (bottom tab bar, native header)
5. **Deep linking** and Universal Links
6. **Native settings/preferences screen**
7. **Biometric authentication** (Face ID / Touch ID)
8. **Native share sheet**
9. **Haptic feedback**

### Approach Options

#### Option A: Capacitor (Recommended -- see Section 4)

Best option for passing review. Lets you add real native features.

#### Option B: PWABuilder iOS Package

PWABuilder can generate an Xcode project, but it produces a minimal WKWebView
wrapper that frequently gets rejected by Apple. Only viable if you plan to add
native features to the generated project afterward.

#### Option C: Custom Swift Wrapper

Build a native Swift app from scratch with WKWebView. Maximum control, but
requires iOS development knowledge.

#### Option D: Services (WebToNative, Median.co, MobiLoud)

Commercial services that wrap your PWA and add native features to pass review.
Costs $500-$5,000+ but handles the Apple submission complexity.

### Push Notifications on iOS

**Critical**: Web Push does NOT work inside WKWebView on iOS. Apple blocks
Service Worker and Push API access in WKWebView. You MUST use native APNs.

**With Capacitor**: Use the `@capacitor/push-notifications` plugin or
`@capacitor-firebase/messaging` to bridge native APNs to your app. The flow:

1. App registers for push with APNs (native side)
2. APNs returns a device token
3. Token is sent to your server (via Capacitor bridge to your web code)
4. Server sends push via APNs (or Firebase FCM which wraps APNs)
5. Native side receives push and can pass it to your web layer

This means you need a **dual push system**:
- Web Push (VAPID) for browser users and Android TWA
- APNs/FCM for the iOS native app

### Apple Developer Account

- **Cost**: $99 USD per year (recurring)
- **Enrollment types**: Individual or Organization
- **Organization requirements**: Legal entity status, D-U-N-S number
- **Individual**: Legal name, two-factor authentication on Apple ID
- **Approval**: Usually a few days; organizations may take longer due to D-U-N-S
  verification
- **URL**: https://developer.apple.com/programs/enroll/

---

## 3. PWABuilder

### What It Does

PWABuilder (https://pwabuilder.com) is a free Microsoft tool that generates
native app packages from a PWA URL. It supports:

- Google Play Store (Android AAB via Bubblewrap)
- Microsoft Store (MSIX)
- Meta Quest Store
- iOS App Store (Xcode project)

### How to Use It

1. Go to https://pwabuilder.com
2. Enter your PWA URL (e.g., https://yourjannah.com)
3. PWABuilder audits your manifest, service worker, and security
4. Click "Package for stores"
5. Choose platform and configure options
6. Download the generated package

### Android Output (Good)

- Uses Bubblewrap under the hood
- Generates a production-ready AAB file
- Handles assetlinks.json generation
- Quality is solid -- this is a Google-supported path

### iOS Output (Problematic)

- Generates an Xcode project with a WKWebView wrapper
- Minimal native features out of the box
- Frequently rejected by Apple Guideline 4.2
- You MUST add native features to the generated project before submitting
- Useful as a starting point, not a finished product

### Gotchas and Limitations

- iOS package is a bare wrapper -- expect Apple rejection without modification
- You still need developer accounts for each store
- You still need to handle signing, screenshots, and store metadata yourself
- The generated Android package is good; the iOS package needs significant work
- No ongoing maintenance -- if something breaks, you fix it yourself

---

## 4. Capacitor (Ionic)

### Overview

Capacitor is Ionic's native runtime that wraps web apps in native containers
with access to native APIs. It is the strongest option for iOS App Store
approval because it lets you add genuine native features.

### Why Capacitor Over TWA/PWABuilder for iOS

| Feature | TWA | PWABuilder | Capacitor |
|---------|-----|------------|-----------|
| Android | Native Chrome | Native Chrome | WebView |
| iOS | N/A | Bare WKWebView | WKWebView + native plugins |
| Native APIs | Limited | None | Full access |
| Push (iOS) | N/A | Web only (broken) | Native APNs |
| App Store approval | N/A | Low chance | Good chance |
| Effort | Low | Low | Medium |

### Setup Process

```bash
# 1. Create a project directory
mkdir yourjannah-native
cd yourjannah-native
npm init -y

# 2. Install Capacitor
npm install @capacitor/core @capacitor/cli

# 3. Initialize Capacitor
npx cap init "YourJannah" "com.yourjannah.app"

# 4. Configure capacitor.config.ts to load your live URL
# (Instead of bundling static files, point to your live site)
```

**capacitor.config.ts** for loading a remote URL:

```typescript
import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.yourjannah.app',
  appName: 'YourJannah',
  // Point to your live WordPress PWA
  server: {
    url: 'https://yourjannah.com',
    cleartext: false
  },
  plugins: {
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert']
    }
  }
};

export default config;
```

```bash
# 5. Add platforms
npx cap add android
npx cap add ios

# 6. Install native plugins
npm install @capacitor/push-notifications
npm install @capacitor/geolocation
npm install @capacitor/share
npm install @capacitor/haptics
npm install @capacitor/status-bar
npx cap sync

# 7. Open in IDE
npx cap open android  # Opens Android Studio
npx cap open ios      # Opens Xcode
```

### Native Plugins for App Store Approval

These plugins help pass Apple Guideline 4.2 by adding genuine native features:

| Plugin | Package | Purpose |
|--------|---------|---------|
| Push Notifications | @capacitor/push-notifications | Native APNs/FCM |
| Geolocation | @capacitor/geolocation | Mosque finder by GPS |
| Share | @capacitor/share | Native share sheet |
| Haptics | @capacitor/haptics | Vibration feedback |
| Status Bar | @capacitor/status-bar | Native status bar control |
| Local Notifications | @capacitor/local-notifications | Prayer time reminders |
| Camera | @capacitor/camera | Profile photos |
| Biometric Auth | capacitor-native-biometric | Face ID / Touch ID |

### Building for Release

**Android:**
```bash
npx cap sync android
# Open Android Studio, Build > Generate Signed Bundle (AAB)
```

**iOS:**
```bash
npx cap sync ios
# Open Xcode, Product > Archive > Distribute App
```

### Capacitor vs TWA for Android

For Android specifically, TWA (via Bubblewrap) is technically superior because
it uses the real Chrome engine. Capacitor uses Android System WebView, which may
have slight rendering differences. However, Capacitor gives you one codebase for
both platforms.

**Recommendation**: Use TWA/Bubblewrap for Android, Capacitor for iOS. Or use
Capacitor for both if you want a single codebase.

---

## 5. Requirements Checklist

### PWA Requirements (Before Any Store Submission)

#### Manifest (manifest.json / manifest.webmanifest)

```json
{
  "name": "YourJannah",
  "short_name": "YourJannah",
  "description": "Mosque community platform for donations, prayer times, and community",
  "start_url": "/",
  "display": "standalone",
  "orientation": "portrait",
  "theme_color": "#00ADEF",
  "background_color": "#FFFFFF",
  "scope": "/",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" },
    { "src": "/icons/icon-maskable-192.png", "sizes": "192x192", "type": "image/png", "purpose": "maskable" },
    { "src": "/icons/icon-maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ]
}
```

#### Service Worker

- Must be registered and active
- Must provide offline fallback page at minimum
- Should cache key assets (app shell, CSS, JS)
- Must handle push events for Web Push notifications

#### HTTPS

- All pages served over HTTPS
- No mixed content (HTTP resources on HTTPS pages)

#### Lighthouse PWA Audit (Target: 80+ Score)

Run: Chrome DevTools > Lighthouse > PWA category

Checklist:
- [ ] Web app manifest meets installability requirements
- [ ] Registers a service worker that controls page and start_url
- [ ] Responds with a 200 when offline (offline fallback page)
- [ ] Has a `<meta name="theme-color">` tag
- [ ] Configured for a custom splash screen
- [ ] Content is sized correctly for the viewport
- [ ] Provides a valid `apple-touch-icon`
- [ ] Manifest has `display` set to `standalone` or `fullscreen`
- [ ] Each page has a URL
- [ ] Page transitions don't feel like they block on the network
- [ ] Redirects HTTP traffic to HTTPS

### Icon Sizes Needed

#### Android (TWA / Capacitor)

| Size | Purpose |
|------|---------|
| 48x48 | mdpi launcher |
| 72x72 | hdpi launcher |
| 96x96 | xhdpi launcher |
| 144x144 | xxhdpi launcher |
| 192x192 | xxxhdpi launcher, PWA manifest |
| 512x512 | Play Store, PWA manifest, splash screen |
| Maskable versions | Same sizes, with safe zone (40% radius circle) |

#### iOS (Capacitor)

| Size | Purpose |
|------|---------|
| 20x20 | iPad notifications (@1x) |
| 29x29 | Settings (@1x) |
| 40x40 | Spotlight (@1x) |
| 60x60 | iPhone app (@1x) |
| 76x76 | iPad app (@1x) |
| 83.5x83.5 | iPad Pro app (@1x) |
| 1024x1024 | App Store listing |
| 120x120 | iPhone app (@2x) |
| 152x152 | iPad app (@2x) |
| 167x167 | iPad Pro app (@2x) |
| 180x180 | iPhone app (@3x), apple-touch-icon |

**Tip**: Generate all sizes from a single 1024x1024 source image. Tools:
- https://makeappicon.com
- https://appicon.co
- Xcode asset catalog auto-generation

#### PWA Manifest Icons (Minimum)

- 192x192 PNG (required)
- 512x512 PNG (required)
- 192x192 maskable PNG (recommended)
- 512x512 maskable PNG (recommended)

### Splash Screens

#### Android

Android auto-generates splash screens from your manifest's `theme_color`,
`background_color`, and `icon` (512x512). No manual splash images needed.

#### iOS

iOS does NOT auto-generate splash screens from the manifest. You need:

- Manual splash images for every iPhone/iPad screen size, OR
- Use Capacitor's `@capacitor/splash-screen` plugin with a single image that
  scales, OR
- Use `apple-touch-startup-image` link tags with media queries

With Capacitor, place a `splash.png` (2732x2732) in the project and Capacitor
handles resizing.

### App Store Metadata

#### Google Play Store

- **App title**: Max 30 characters
- **Short description**: Max 80 characters
- **Full description**: Max 4,000 characters
- **App icon**: 512x512 PNG with alpha, max 1 MB
- **Feature graphic**: 1024x500 JPEG or PNG (no alpha)
- **Screenshots (phone)**: Min 2, max 8; recommended 1080x1920 (9:16 portrait)
- **Screenshots (tablet)**: At least 1 if you support tablets
- **Privacy policy URL**: Required
- **Content rating**: Complete the IARC questionnaire
- **Target audience**: Required declaration

#### Apple App Store

- **App name**: Max 30 characters
- **Subtitle**: Max 30 characters
- **Description**: Max 4,000 characters
- **Promotional text**: Max 170 characters
- **Keywords**: Max 100 characters
- **App icon**: 1024x1024 PNG (no alpha, no rounded corners)
- **Screenshots (iPhone)**: 1290x2796 (6.9") mandatory; up to 10 per locale
- **Screenshots (iPad)**: 2064x2752 (13") mandatory if iPad supported
- **Privacy policy URL**: Required
- **App category**: Required (likely: Lifestyle or Social Networking)
- **Age rating**: Complete Apple's questionnaire
- **Support URL**: Required

---

## 6. Recommended Approach

### Simplest Path to Both Stores

**Android: Bubblewrap (TWA)** -- Simplest, fastest, Google-supported.
Your web push notifications work out of the box via notification delegation.

**iOS: Capacitor with native plugins** -- Required to pass Apple review.
You must add native push (APNs), native navigation, and at least 2-3 native
features.

### Step-by-Step Plan

#### Phase 1: PWA Readiness (Week 1)

1. Audit manifest.json -- ensure all required fields are present
2. Verify service worker works and provides offline fallback
3. Run Lighthouse PWA audit, fix any failures
4. Generate all icon sizes from 1024x1024 source
5. Create iOS splash screen assets
6. Ensure HTTPS with no mixed content

#### Phase 2: Android via Bubblewrap (Week 2)

1. Register Google Play Developer account ($25)
2. Install Bubblewrap CLI
3. Initialize project from manifest URL
4. Generate signing keystore
5. Build AAB
6. Deploy assetlinks.json to /.well-known/ on your domain
7. Test APK on physical device
8. Create Play Store listing (screenshots, descriptions, graphics)
9. Upload AAB to Play Console
10. Submit for review

**Expected Android review time**: 1-3 days for new apps.

#### Phase 3: iOS via Capacitor (Weeks 3-4)

1. Register Apple Developer account ($99/year)
2. Set up Capacitor project pointing to live URL
3. Add native plugins:
   - Push Notifications (APNs via @capacitor/push-notifications)
   - Geolocation (mosque finder)
   - Local Notifications (prayer time reminders)
   - Share (native share sheet)
   - Haptics (feedback)
   - Status Bar (native look and feel)
4. Add native UI elements:
   - Bottom tab bar (native, not web)
   - Native splash screen
   - Native loading states
5. Implement dual push notification system:
   - Server-side: send to both Web Push and APNs/FCM
   - Client-side: register with APNs on iOS, Web Push on web/Android
6. Test on physical iPhone
7. Create App Store listing (screenshots, metadata)
8. Archive and upload via Xcode
9. Submit for App Store review

**Expected iOS review time**: 1-7 days, but rejection cycles add 3-7 days each.

#### Phase 4: Post-Launch (Week 5+)

1. Monitor crash reports and reviews
2. Set up CI/CD for future updates (Bubblewrap rebuild for Android, Xcode
   archive for iOS)
3. Respond to any Apple review feedback promptly

---

## 7. Cost Summary

| Item | Cost | Frequency |
|------|------|-----------|
| Google Play Developer Account | $25 | One-time |
| Apple Developer Account | $99 | Annual |
| Bubblewrap CLI | Free | -- |
| Capacitor | Free (open source) | -- |
| PWABuilder | Free | -- |
| Xcode | Free (macOS required) | -- |
| Android Studio | Free | -- |
| **Total Year 1** | **$124** | -- |
| **Total Recurring** | **$99/year** | Apple only |

**Hardware note**: You need a Mac to build and submit iOS apps via Xcode. If
you don't have one, options include:
- Mac Mini (from ~$599)
- MacBook Air (from ~$999)
- Cloud Mac services (MacStadium, AWS EC2 Mac -- ~$30-100/month)
- GitHub Actions with macOS runners (free tier has limited minutes)

---

## 8. Timeline

| Week | Task | Deliverable |
|------|------|-------------|
| 1 | PWA audit and asset preparation | Lighthouse 80+, all icons, splash screens |
| 2 | Android build and submission | AAB on Google Play, assetlinks.json live |
| 2 | Google Play review | App live on Play Store |
| 3 | Capacitor project setup, native plugins | Working iOS build with native features |
| 4 | iOS testing and App Store submission | IPA uploaded to App Store Connect |
| 4-5 | Apple review (may need revision cycles) | App live on App Store |

**Total estimated time**: 4-6 weeks from PWA readiness to both stores.

**Risk factor**: Apple rejection. Budget 1-2 extra weeks for potential rejection
and resubmission. The more native features you add upfront, the better your
chances of first-pass approval.

---

## Quick Reference: Key Commands

```bash
# --- Android (Bubblewrap) ---
npm install -g @bubblewrap/cli
mkdir yourjannah-android && cd yourjannah-android
bubblewrap init --manifest https://yourjannah.com/manifest.json
bubblewrap build
bubblewrap install  # test on device

# --- iOS (Capacitor) ---
mkdir yourjannah-native && cd yourjannah-native
npm init -y
npm install @capacitor/core @capacitor/cli
npx cap init "YourJannah" "com.yourjannah.app"
npx cap add ios
npm install @capacitor/push-notifications @capacitor/geolocation @capacitor/share @capacitor/haptics
npx cap sync ios
npx cap open ios  # opens Xcode
```
