# Call Flow Diagram for FusionPBX
A visual call flow diagram app for [FusionPBX](https://www.fusionpbx.com). Select any starting point
 - inbound route
 - IVR menu
 - ring group
 - call flow
 - time condition
 - extension
 - call center
and the app traces the full routing path and renders it as an interactive diagram.

## Features
- Color-coded nodes by type (inbound route, IVR, ring group, call flow, time condition, extension, voicemail, hangup, external)
- Live extension registration status inside ring group nodes (🟢 registered / 🔴 unregistered)
- Extensions are listed inline inside ring group nodes rather than as separate connected boxes
- Freely draggable canvas. Move nodes in any direction after the diagram renders
- Double-click any node to open its edit page in a new tab
- Download the diagram as a PNG
- Fit view button to re-center the diagram

## Requirements
- FusionPBX 5.x or later
- PHP 8.0+

## Installation

### 1. Clone the repository

SSH into your FusionPBX server and clone the app into the FusionPBX apps directory:

```bash
cd /var/www/fusionpbx/app
git clone https://github.com/tony1661/fusionpbx-app-call_flow_diagram call_flow_diagram
```

### 2. Set ownership

Make sure the web server user owns the new directory:

```bash
chown -R www-data:www-data /var/www/fusionpbx/app/call_flow_diagram
```

> If your server uses a different web user (e.g. `nginx` or `apache`), replace `www-data` accordingly.

### 3. Register the app with FusionPBX

Log into the FusionPBX web interface as a superadmin, then go to:

**Advanced → Upgrade**

Click **App Defaults** to register the app's menu entry and permissions. This step adds the *Call Flow Diagram* entry to the **Reports** menu and grants access to the appropriate groups.

### 4. Reload the menu

If the menu entry does not appear immediately, go to:

**Advanced → Menu Manager** → select your menu → click **Defaults**

Then log out and back in for the menu to refresh.

## Permissions

The following permission is registered automatically during the App Defaults step:

| Permission | Default Groups |
|---|---|
| `call_flow_diagram_view` | superadmin, admin, user |

Access can be adjusted per group in **Advanced → Group Manager**.

The registration status indicators (🟢/🔴) inside ring group nodes require the additional FusionPBX core permission:

| Permission | Where to grant it |
|---|---|
| `extension_registered` | Advanced → Group Manager → select group → add permission |

Without `extension_registered`, ring group members still display but show a ☎ icon instead of a status color.

## Usage

1. Navigate to **Reports → Call Flow Diagram**
2. Select a **Starting Type** from the dropdown (e.g. Inbound Routes)
3. Select the specific **Destination** you want to trace
4. Click **Generate Diagram**

Once the diagram renders:

- **Drag** any node to reposition it
- **Scroll / pinch** to zoom in and out
- **Double-click** a node to open its edit page in a new tab
- Use the **Fit View** button to re-center the diagram
- Use the **Download PNG** button to export the diagram as an image
