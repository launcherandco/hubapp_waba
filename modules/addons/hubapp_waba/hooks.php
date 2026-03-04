<?php
/**
 * HubApp WABA WHMCS - Hooks Oficiais
 * Versão: 1.1.0 (Auto-Login Ready)
 */

if (!defined("WHMCS")) die("Access Denied");

use WHMCS\Database\Capsule;
use HubAppWabaModule\HubAppWabaClient;

require_once __DIR__ . '/lib/HubAppWabaClient.php';

/**
 * Função para gerar URL de Auto-Login via Single Sign-On do WHMCS
 */
function waba_get_autologin_url($clientId, $destinationPath) {
    $results = localAPI('CreateSsoToken', [
        'client_id' => $clientId,
        'destination' => 'sso:custom_redirect',
        'sso_redirect_path' => $destinationPath
    ]);
    
    if (isset($results['result']) && $results['result'] == 'success') {
        return $results['redirect_url'];
    }
    
    // Fallback de segurança para URL normal caso o SSO falhe
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    return rtrim($systemUrl, '/') . '/' . ltrim($destinationPath, '/');
}

/**
 * Função de despacho inteligente. Decide entre template padrão ou Auto-Login.
 */
function waba_dispatch($eventKey, $uid, $standardParams, $autoLoginPath = null, $autoLoginParams = null) {
    $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_waba')->pluck('value', 'setting');
    
    $tplAutoLogin = $config['tplname_' . $eventKey . '_autologin'] ?? '';
    $tplStandard = $config['tplname_' . $eventKey] ?? '';

    // 1. Dispara versão Auto-Login se estiver configurada
    if (!empty($tplAutoLogin) && $autoLoginPath !== null && $autoLoginParams !== null) {
        $url = waba_get_autologin_url($uid, $autoLoginPath);
        $finalParams = [];
        
        // Substitui a tag pela URL gerada
        foreach ($autoLoginParams as $p) {
            $finalParams[] = ($p === '{autologin_url}') ? $url : $p;
        }
        return HubAppWabaClient::sendTemplate($uid, $tplAutoLogin, $finalParams);
    }

    // 2. Dispara versão Padrão se o Auto-Login não estiver configurado
    if (!empty($tplStandard)) {
        return HubAppWabaClient::sendTemplate($uid, $tplStandard, $standardParams);
    }
}

// 1. Fatura Gerada
add_hook('InvoiceCreationPreEmail', 1, function($vars) {
    $inv = Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->first();
    if (!$inv) return;
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    $normalUrl = $systemUrl . "viewinvoice.php?id=" . $vars['invoiceid'];
    $path = "viewinvoice.php?id=" . $vars['invoiceid'];

    waba_dispatch('InvoiceCreated', $cli->id, 
        [$cli->firstname, $vars['invoiceid'], $inv->total, fromMySQLDate($inv->duedate), $normalUrl],
        $path,
        [$cli->firstname, $vars['invoiceid'], $inv->total, fromMySQLDate($inv->duedate), '{autologin_url}']
    );
});

// 2. Pagamento Confirmado
// Variáveis: {{1}} Nome, {{2}} ID da Fatura
add_hook('InvoicePaid', 1, function($vars) {
    $inv = Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    
    // Disparo simples, sem parâmetros de auto-login
    waba_dispatch('InvoicePaid', $cli->id, [$cli->firstname, $vars['invoiceid']]);
});

// 3, 4 e 5. Lembretes de Fatura
add_hook('InvoicePaymentReminder', 1, function($vars) {
    $inv = Capsule::table('tblinvoices')->where('id', $vars['invoiceid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $inv->userid)->first();
    
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    $normalUrl = $systemUrl . "viewinvoice.php?id=" . $vars['invoiceid'];
    $path = "viewinvoice.php?id=" . $vars['invoiceid'];
    
    $type = ($vars['type'] == 'first') ? 'First' : (($vars['type'] == 'second') ? 'Second' : 'Third');

    waba_dispatch('InvoicePaymentReminder' . $type, $cli->id, 
        [$cli->firstname, $vars['invoiceid'], fromMySQLDate($inv->duedate), $normalUrl],
        $path,
        [$cli->firstname, $vars['invoiceid'], fromMySQLDate($inv->duedate), '{autologin_url}']
    );
});

// 6. Resposta em Ticket
add_hook('TicketAdminReply', 1, function($vars) {
    $ticket = Capsule::table('tbltickets')->where('id', $vars['ticketid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $ticket->userid)->first();
    
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    $normalUrl = $systemUrl . "viewticket.php?tid=" . $ticket->tid . "&c=" . $ticket->c;
    $path = "viewticket.php?tid=" . $ticket->tid . "&c=" . $ticket->c;

    waba_dispatch('TicketAdminReply', $cli->id, 
        [$cli->firstname, $ticket->title, $normalUrl],
        $path,
        [$cli->firstname, $ticket->title, '{autologin_url}']
    );
});

// 7. Admin: Novo Ticket
add_hook('TicketOpen', 1, function($vars) {
    $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_waba')->pluck('value', 'setting');
    if ($config['tplname_TicketOpenAdmin'] && $config['admin_whatsapp']) {
        $clientName = ($vars['userid']) ? Capsule::table('tblclients')->where('id', $vars['userid'])->value('firstname') : "Visitante";
        HubAppWabaClient::sendTemplateByNumber($config['admin_whatsapp'], $config['tplname_TicketOpenAdmin'], [
            $vars['subject'], 
            $clientName, 
            $vars['priority']
        ]);
    }
});

// 8. Alerta de Login Admin
add_hook('AdminLogin', 1, function($vars) {
    $config = Capsule::table('tbladdonmodules')->where('module', 'hubapp_waba')->pluck('value', 'setting');
    if ($config['tplname_AdminLogin'] && $config['admin_whatsapp']) {
        HubAppWabaClient::sendTemplateByNumber($config['admin_whatsapp'], $config['tplname_AdminLogin'], [$vars['username']]);
    }
});

// 9. Serviço Ativado
add_hook('AfterModuleCreate', 1, function($vars) {
    $p = $vars['params'];
    $firstName = Capsule::table('tblclients')->where('id', $p['userid'])->value('firstname');
    
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    $normalUrl = rtrim($systemUrl, '/') . "/clientarea.php?action=productdetails&id=" . $p['serviceid'];
    $path = "clientarea.php?action=productdetails&id=" . $p['serviceid'];

    waba_dispatch('AfterModuleCreate', $p['userid'], 
        [$firstName, $p['domain'], $normalUrl],
        $path,
        [$firstName, $p['domain'], '{autologin_url}']
    );
});

// 10. Serviço Suspenso
add_hook('AfterModuleSuspend', 1, function($vars) {
    $p = $vars['params'];
    $firstName = Capsule::table('tblclients')->where('id', $p['userid'])->value('firstname');
    
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    $normalUrl = rtrim($systemUrl, '/') . "/clientarea.php?action=productdetails&id=" . $p['serviceid'];
    $path = "clientarea.php?action=productdetails&id=" . $p['serviceid'];

    waba_dispatch('AfterModuleSuspend', $p['userid'], 
        [$firstName, $p['domain'], $normalUrl],
        $path,
        [$firstName, $p['domain'], '{autologin_url}']
    );
});

// 11. Expiração de Domínio
add_hook('DomainRenewalNotice', 1, function($vars) {
    $dom = Capsule::table('tbldomains')->where('id', $vars['domainid'])->first();
    $cli = Capsule::table('tblclients')->where('id', $dom->userid)->first();
    
    $systemUrl = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->value('value');
    $normalUrl = rtrim($systemUrl, '/') . "/cart.php?a=view"; 
    $path = "cart.php?a=view";

    waba_dispatch('DomainRenewalNotice', $cli->id, 
        [$cli->firstname, $dom->domain, $vars['daysuntilexpiry'], fromMySQLDate($dom->expirydate), $normalUrl],
        $path,
        [$cli->firstname, $dom->domain, $vars['daysuntilexpiry'], fromMySQLDate($dom->expirydate), '{autologin_url}']
    );
});
