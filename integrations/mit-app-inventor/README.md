# Amor Palace Hotel - MIT App Inventor

This folder contains an MIT App Inventor WebViewer project for the Amor Palace Hotel website.

## Import the project

1. Deploy the Symfony website to a public host with HTTPS. The App Inventor Companion or a phone cannot reach `localhost` on your development computer.
2. In MIT App Inventor, choose **Projects > Import project (.aia) from my computer**.
3. Import `AmorPalaceHotel.aia`.
4. Open `Screen1` in Designer and select `WebViewer1`.
5. Set **HomeUrl** to the deployed website root, for example `https://your-domain.example/`.
6. Connect the Companion or build the Android app to test it.

The imported project currently uses `https://YOUR-DEPLOYED-HOST/` as a placeholder. Replace it with the real public HTTPS address before testing. Do not use database credentials or private API keys in the App Inventor project.

## How it works

The WebViewer displays the existing responsive website, so its hotel search, account, and booking pages continue to use the Symfony backend. The backend and website must remain deployed and reachable for the app to work.

This is a WebViewer shell, not a native App Inventor implementation. The App Inventor project does not bypass login, payments, or server-side authorization.
