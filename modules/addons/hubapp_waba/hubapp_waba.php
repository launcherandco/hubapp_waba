<?php
/**
 * HubApp WABA WHMCS (API Oficial Meta)
 * @author     HubApp / Licencas.Digital
 * @version    1.1.0 (Auto-Login Support)
 */

if (!defined("WHMCS")) die("Access Denied");

use WHMCS\Database\Capsule;

function hubapp_waba_config() {
    $customFields = [0 => "Usar apenas telefone padrão"];
    try {
        $fields = Capsule::table('tblcustomfields')->where('type', 'client')->get(['id', 'fieldname']);
        foreach ($fields as $field) { $customFields[$field->id] = $field->fieldname; }
    } catch (\Exception $e) {}

    return [
        "name" => "HubApp WABA WHMCS",
        "description" => "Integração oficial via WhatsApp Business API (Meta). Requer templates aprovados.",
        "author" => "HubApp",
        "version" => "1.1.0",
        "fields" => [
            "api_endpoint" => ["FriendlyName" => "Endpoint WABA", "Type" => "text", "Size" => "80", "Description" => "Ex: https://graph.facebook.com/v21.0/ID_DO_NUMERO/"],
            "api_token" => ["FriendlyName" => "API Token Meta", "Type" => "password", "Size" => "80"],
            "whatsapp_field_id" => ["FriendlyName" => "Campo WhatsApp", "Type" => "dropdown", "Options" => $customFields],
            "admin_whatsapp" => ["FriendlyName" => "WhatsApp Admin", "Type" => "text", "Size" => "25", "Description" => "Número com DDI (Ex: 5534999999999)"],
            "language_code" => ["FriendlyName" => "Idioma", "Type" => "text", "Default" => "pt_BR"],
            "use_header" => ["FriendlyName" => "Ativar Header", "Type" => "yesno"],
            "header_text" => ["FriendlyName" => "Texto Header", "Type" => "text", "Default" => "LD | HubApp"],
            "use_footer" => ["FriendlyName" => "Ativar Footer", "Type" => "yesno"],
            "footer_text" => ["FriendlyName" => "Texto Footer", "Type" => "text", "Default" => "LD | HubApp - Launcher & Co."],
            "manual_template" => ["FriendlyName" => "Template Manual/Teste", "Type" => "text", "Description" => "Nome do template aprovado na Meta."],
        ]
    ];
}

function hubapp_waba_output($vars) {
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">';
    require_once __DIR__ . '/lib/HubAppWabaClient.php';
    
    // Lista expandida para incluir os modelos _autologin
    $events = [
        'InvoiceCreated' => 'Fatura Gerada (Padrão)',
        'InvoiceCreated_autologin' => 'Fatura Gerada (Com Auto-Login)',
        'InvoicePaid' => 'Pagamento Confirmado (Padrão)',
        'InvoicePaymentReminderFirst' => '1º Aviso de Atraso (Padrão)',
        'InvoicePaymentReminderFirst_autologin' => '1º Aviso de Atraso (Com Auto-Login)',
        'InvoicePaymentReminderSecond' => '2º Aviso de Atraso (Padrão)',
        'InvoicePaymentReminderSecond_autologin' => '2º Aviso de Atraso (Com Auto-Login)',
        'InvoicePaymentReminderThird' => 'Aviso Crítico (Padrão)',
        'InvoicePaymentReminderThird_autologin' => 'Aviso Crítico (Com Auto-Login)',
        'TicketAdminReply' => 'Resposta em Ticket (Padrão)',
        'TicketAdminReply_autologin' => 'Resposta em Ticket (Com Auto-Login)',
        'TicketOpenAdmin' => 'Admin: Novo Ticket',
        'AdminLogin' => 'Alerta de Login Admin',
        'AfterModuleCreate' => 'Serviço Ativado (Padrão)',
        'AfterModuleCreate_autologin' => 'Serviço Ativado (Com Auto-Login)',
        'AfterModuleSuspend' => 'Serviço Suspenso (Padrão)',
        'AfterModuleSuspend_autologin' => 'Serviço Suspenso (Com Auto-Login)',
        'DomainRenewalNotice' => 'Expiração de Domínio (Padrão)',
        'DomainRenewalNotice_autologin' => 'Expiração de Domínio (Com Auto-Login)',
    ];

    if (isset($_POST['test_waba'])) {
        $adminNum = $vars['admin_whatsapp'];
        if (empty($adminNum)) {
            echo '<div class="alert alert-danger">❌ Erro: Configure o WhatsApp Admin antes de testar.</div>';
        } else {
            $msgTest = "HubApp WABA: Teste de conexão oficial Meta com Auto-Login realizado com sucesso!";
            $res = \HubAppWabaModule\HubAppWabaClient::sendTemplateByNumber($adminNum, $vars['manual_template'], [$msgTest]);
            echo '<div class="alert alert-info"><strong>Resposta da API Meta:</strong><br><pre>' . htmlspecialchars($res) . '</pre></div>';
        }
    }

    if (isset($_POST['send_manual_waba'])) {
        if ($_POST['target_client'] && $_POST['manual_body']) {
            \HubAppWabaModule\HubAppWabaClient::sendTemplate($_POST['target_client'], $vars['manual_template'], [$_POST['manual_body']]);
            echo '<div class="alert alert-success">✅ Mensagem manual disparada!</div>';
        }
    }

    if (isset($_POST['save_waba'])) {
        foreach ($events as $key => $name) {
            Capsule::table('tbladdonmodules')->updateOrInsert(['module' => 'hubapp_waba', 'setting' => 'tplname_' . $key],['value' => $_POST['tplname_' . $key]]);
        }
        echo '<div class="alert alert-success">✅ Configurações e templates salvos!</div>';
    }

    echo '<h2><i class="fab fa-whatsapp" style="color:#25D366"></i> Central HubApp WABA</h2>';

    echo '<div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-paper-plane"></i> Envio Manual e Teste</h3></div>
        <div class="panel-body">
            <form method="post" style="margin-bottom:20px;">
                <button type="submit" name="test_waba" class="btn btn-info"><i class="fas fa-plug"></i> Testar Conexão no WhatsApp Admin</button>
            </form>
            <hr>
            <form method="post">
                <div class="form-group">
                    <label>Cliente:</label>
                    <select name="target_client" class="form-control" style="width: 100%;">
                        <option value="">-- Selecione --</option>';
                        $clients = Capsule::table('tblclients')->orderBy('firstname', 'asc')->limit(1000)->get(['id','firstname','lastname']);
                        foreach($clients as $c){ echo '<option value="'.$c->id.'">#'.$c->id.' - '.$c->firstname.' '.$c->lastname.'</option>'; }
    echo '          </select>
                </div>
                <div class="form-group">
                    <label>Conteúdo (Variável {{1}}):</label>
                    <textarea name="manual_body" class="form-control" rows="3" placeholder="Digite o aviso..."></textarea>
                </div>
                <button type="submit" name="send_manual_waba" class="btn btn-primary btn-block">Enviar Agora</button>
            </form>
        </div>
    </div>';

    echo '<form method="post">
    <div class="panel panel-default">
        <div class="panel-heading"><h3 class="panel-title"><i class="fas fa-list"></i> Mapeamento de Templates Meta</h3></div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Evento WHMCS</th><th>Nome do Template Aprovado na Meta</th></tr></thead>
                <tbody>';
    // Mapa de sugestões amigáveis para os placeholders
    $hints = [
        'InvoiceCreated' => 'fatura_gerada',
        'InvoicePaid' => 'fatura_paga',
        'InvoicePaymentReminderFirst' => 'fatura_atrasada',
        'InvoicePaymentReminderSecond' => 'fatura_atrasada_2',
        'InvoicePaymentReminderThird' => 'fatura_atrasada_3',
        'TicketAdminReply' => 'ticket_resposta',
        'TicketOpenAdmin' => 'admin_novo_ticket',
        'AdminLogin' => 'admin_login',
        'AfterModuleCreate' => 'servico_ativo',
        'AfterModuleSuspend' => 'servico_suspenso',
        'DomainRenewalNotice' => 'dominio_expirando',
    ];

    foreach ($events as $key => $name) {
        $val = Capsule::table('tbladdonmodules')->where('module' , 'hubapp_waba')->where('setting', 'tplname_' . $key)->value('value');
        
        $isAutoLogin = strpos($key, '_autologin') !== false;
        $rowStyle = $isAutoLogin ? 'background-color: #f0f8ff;' : '';
        
        // Gera o placeholder dinâmico baseado no mapa acima
        $baseKey = str_replace('_autologin', '', $key);
        $hintText = isset($hints[$baseKey]) ? $hints[$baseKey] : 'nome_do_template';
        $placeholder = $isAutoLogin ? "ex: {$hintText}_autologin" : "ex: {$hintText}";
        
        echo '<tr style="'.$rowStyle.'"><td><strong>'.$name.'</strong></td><td><input type="text" name="tplname_'.$key.'" class="form-control" value="'.htmlspecialchars((string)$val).'" placeholder="'.$placeholder.'"></td></tr>';
    }
    echo '</tbody></table></div>
        <div class="panel-footer"><button type="submit" name="save_waba" class="btn btn-success"><i class="fas fa-save"></i> Salvar Mapeamento</button></div>
    </div></form>';

    echo '<div class="text-center" style="margin-top:20px; color:#888;">
        <small>HubApp WABA v1.1.0 | Oficial Meta | Suporte: <a href="https://licencas.digital" target="_blank">licencas.digital</a></small>
    </div>';

}
