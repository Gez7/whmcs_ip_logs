<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS'))
	die('You cannot access this file directly.');

define("MODULENAME", 'ip_logs');

// Inserts User ID, Last IP Address and Last Login Datetime to mod_ip_logs. Executes on user login.
add_hook('UserLogin', 1, function ($vars) {
    if (!isset($vars['user'])) return;

    // Handle WHMCS 8+ User object or older array structures
    $userid = is_object($vars['user']) ? $vars['user']->id : $vars['user']['id'];
    
    // Utilize WHMCS's evaluated IP rather than raw SERVER variables to bypass Cloudflare proxy issues.
    $ip = is_object($vars['user']) ? $vars['user']->last_ip : ($vars['user']['last_ip'] ?? null);
    $datetime = is_object($vars['user']) ? $vars['user']->last_login : ($vars['user']['last_login'] ?? null);

    // Ultimate fallback for proxy resolution if the object properties happen to be empty
    if (empty($ip)) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // If X_FORWARDED_FOR has multiple IPs, grab the first one (the original client)
        $ipList = explode(',', $ip);
        $ip = trim($ipList[0]);
    }
    
    if (empty($datetime)) {
        $datetime = date("Y-m-d H:i:s");
    }

    try {
        Capsule::table('mod_ip_logs')->insert(
            ['user_id' => $userid, 'ip' => $ip, 'login_datetime' => $datetime]
        );
    } catch (Exception $e) {
        logActivity("Client IP Logs Addon Error (UserLogin): " . $e->getMessage());
    }
});

// Delete IP logs safely when deleting a User (WHMCS 8+)
add_hook('UserDelete', 1, function($vars) {
    if (!isset($vars['user'])) return;

    $userid = is_object($vars['user']) ? $vars['user']->id : $vars['user']['id'];
    try {
        Capsule::table('mod_ip_logs')->where('user_id', '=', $userid)->delete();
    } catch (Exception $e) {
        logActivity("Client IP Logs Addon Error (UserDelete): " . $e->getMessage());
    }
});

// Display user ip history for the client on clientssummary
add_hook('AdminAreaClientSummaryPage', 1, function ($vars) {
    $userId = $vars['userid'];

    // Discover the Users associated with this Client (WHMCS 8+)
    $authUsers = [$userId]; // default to backwards compatibility
    if (Capsule::schema()->hasTable('tblusers_clients')) {
        $foundUsers = Capsule::table('tblusers_clients')
            ->where('client_id', $userId)
            ->pluck('auth_user_id')
            ->toArray();
        if (!empty($foundUsers)) {
            $authUsers = array_merge($authUsers, $foundUsers);
        }
    }

    $ipTable = Capsule::table('mod_ip_logs')
        ->select('ip', 'login_datetime')
        ->whereIn('user_id', array_unique($authUsers))
        ->orderBy('login_datetime', 'desc')
        ->get();

    $output = '
    <div class="panel panel-default" id="ip-history-widget-wrapper" style="margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div class="panel-heading" style="background-color: #f8f9fa; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; padding: 10px 15px;">
            <h3 class="panel-title" style="margin: 0; font-size: 14px; font-weight: 600; color: #333;"><i class="fas fa-list fa-fw"></i> IP History</h3>
        </div>
        <div class="panel-body" style="padding: 15px;">
            <input class="form-control" type="search" placeholder="Search IP History..." aria-label="Search" id="searchIpHistory" style="margin-bottom: 15px; border-radius: 4px;">
            <div class="table-responsive" style="max-height: 200px; overflow-y: auto; border: 1px solid #eee; border-radius: 4px;">
                <table class="table table-striped table-hover table-condensed" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="cursor: pointer; background: #fff; position: sticky; top: 0; box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);" class="sortable-header">Date <i class="fas fa-sort text-muted pull-right"></i></th>
                            <th style="cursor: pointer; background: #fff; position: sticky; top: 0; box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);" class="sortable-header">IP Address <i class="fas fa-sort text-muted pull-right"></i></th>
                        </tr>
                    </thead>
                    <tbody id="ipHistoryTableBody">';

    if (count($ipTable) > 0) {
        foreach ($ipTable as $row) {
            $formattedDate = date("Y-m-d H:i:s", strtotime($row->login_datetime));
            $output .= '<tr>
                <td style="white-space: nowrap;">' . htmlspecialchars($formattedDate) . '</td>
                <td><span class="label label-info" style="font-size: 11px;">' . htmlspecialchars($row->ip) . '</span></td>
            </tr>';
        }
    } else {
        $output .= '<tr><td colspan="2" class="text-center text-muted" style="padding: 20px;">No IP history found.</td></tr>';
    }

    $output .= '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';

    $script = '<script>
    jQuery(document).ready(function($) {
        // Widget Layout Placement 
        var widget = $("#ip-history-widget-wrapper");
        
        // Priority targets: Lara Theme panels -> Standard layout columns -> Ultimate fallback wrapper
        var laraTarget = $(".client-summary-panels, .row.client-summary, .panel-client-summary");
        var clientContainerBottomLeft = $("#clientsummarycontainer .row .col-lg-3, #clientsummarycontainer .row .col-md-3").last();
        var standardClientProfilePanel = $(".client-profile-panels, .client-summary-widgets");

        if (laraTarget.length > 0) {
            laraTarget.first().append(widget);
        } else if (standardClientProfilePanel.length > 0) {
            var parentTarget = standardClientProfilePanel.parent();
            if(parentTarget && parentTarget.length > 0) {
                parentTarget.append(widget);
            } else {
                standardClientProfilePanel.append(widget);
            }
        } else if (clientContainerBottomLeft.length > 0) {
            clientContainerBottomLeft.append(widget);
        } else {
            // Ultimate fallback cleanly drops it at the bottom
            $("#clientsummarycontainer").append(widget);
        }

        // Live Search Filter
        $("#searchIpHistory").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#ipHistoryTableBody tr").filter(function() {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(value) > -1);
            });
        });

        // Simple HTML Table Sorting
        $(".sortable-header").click(function(){
            var table = $(this).parents("table").eq(0);
            var tbody = table.find("tbody");
            var rows = tbody.find("tr").toArray().sort(comparer($(this).index()));
            this.asc = !this.asc;
            if (!this.asc){ rows = rows.reverse(); }
            for (var i = 0; i < rows.length; i++){ tbody.append(rows[i]); }
        });

        function comparer(index) {
            return function(a, b) {
                var valA = getCellValue(a, index), valB = getCellValue(b, index);
                return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.toString().localeCompare(valB);
            };
        }
        function getCellValue(row, index){ 
            return $(row).children("td").eq(index).text(); 
        }
    });
    </script>';
    
    return $output . $script;
});