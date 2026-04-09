# WHMCS Client IP Login History
This WHMCS addon module automatically logs User IP addresses upon authentication and displays their connection history directly within the WHMCS Admin Client Summary page.

## Features
- **WHMCS 8+ Compatible**: Fully supports modern WHMCS environments where "Users" and "Clients" are decoupled, seamlessly discovering and linking all users associated with a client.
- **Modern Bootstrap UI**: Renders a beautiful, native-looking panel widget equipped with a live search filter and column sorting capabilities. 
- **Accurate Deletions**: Intelligently cleans up logs upon `UserDelete` to maintain complete data integrity.

## Installation
1. Upload the `ip_logs` folder to the `modules/addons` directory in your WHMCS installation.
2. Login to your WHMCS admin area and navigate to `Setup` > `Addon Modules`.
3. Find the `Client IP Logs` module in the list and click `Activate`.
4. Grant the necessary access control permissions to your admin role.
5. Save your changes.

## Usage
Once the plugin is installed and activated, it will automatically start logging authenticating IPs in the background. You can view the IP history on the clientssummary page for any client.

![screenshot](https://github.com/hivedc/whmcs_ip_logs/blob/main/ip-history.png?raw=true)

# About Hive
Hive Data center (hivedatacenter.com) is a dedicated server and colocation provider in Canada.
