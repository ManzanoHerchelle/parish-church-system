<?php
/**
 * UI Helper Functions
 * Convenient functions for rendering UI components
 */

require_once __DIR__ . '/../Components/UIComponents.php';

/**
 * Create and render a card
 */
function card($header = '', $body = '', $footer = '') {
    $card = new Card($header);
    if ($body) $card->setBody($body);
    if ($footer) $card->setFooter($footer);
    return $card->render();
}

/**
 * Create and render an alert
 */
function alert($message, $type = 'info', $dismissible = true) {
    if (!$message) return '';
    $alert = new Alert($message, $type);
    $alert->setDismissible($dismissible);
    return $alert->render();
}

/**
 * Create and render a button
 */
function button($label, $type = 'primary', $href = '', $icon = '', $class = '') {
    $iconHtml = $icon ? "<i class=\"bi bi-$icon\"></i> " : '';
    $btnClass = "btn btn-$type $class";
    
    if ($href) {
        return "<a href=\"$href\" class=\"$btnClass\">$iconHtml$label</a>";
    } else {
        return "<button type=\"button\" class=\"$btnClass\">$iconHtml$label</button>";
    }
}

/**
 * Create and render a badge
 */
function badge($label, $type = 'primary') {
    return "<span class=\"badge bg-$type\">$label</span>";
}

/**
 * Format a date
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Format a datetime
 */
function formatDateTime($date, $format = 'M d, Y h:i A') {
    return date($format, strtotime($date));
}

/**
 * Create and render a modal
 */
function modal($id, $title, $body, $footer = '', $size = 'modal-md') {
    $m = new Modal($id, $title);
    $m->setBody($body);
    if ($footer) $m->setFooter($footer);
    $m->setSize($size);
    return $m->render();
}

/**
 * Create and render a table
 */
function dataTable($headers, $rows, $options = []) {
    $striped = isset($options['striped']) && $options['striped'] ? 'table-striped' : '';
    $hover = isset($options['hover']) && $options['hover'] ? 'table-hover' : '';
    
    $html = "<table class=\"table $striped $hover\">";
    
    // Headers
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        $html .= "<th>$header</th>";
    }
    $html .= '</tr></thead>';
    
    // Rows
    $html .= '<tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= "<td>$cell</td>";
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    
    return $html;
}

/**
 * Create a status badge for documents
 */
function documentStatusBadge($status) {
    $classes = [
        'pending' => 'badge-warning',
        'processing' => 'badge-info',
        'ready' => 'badge-success',
        'completed' => 'badge-success',
        'rejected' => 'badge-danger'
    ];
    
    $class = $classes[$status] ?? 'badge-secondary';
    return "<span class=\"badge $class\">" . ucfirst($status) . "</span>";
}

/**
 * Create a payment status badge
 */
function paymentStatusBadge($status) {
    $classes = [
        'unpaid' => 'badge-danger',
        'pending' => 'badge-warning',
        'paid' => 'badge-success'
    ];
    
    $class = $classes[$status] ?? 'badge-secondary';
    return "<span class=\"badge $class\">" . ucfirst($status) . "</span>";
}

/**
 * Render a form group
 */
function formGroup($name, $label, $type = 'text', $value = '', $required = false, $options = []) {
    $requiredAttr = $required ? 'required' : '';
    $placeholder = isset($options['placeholder']) ? " placeholder=\"{$options['placeholder']}\"" : '';
    $class = isset($options['class']) ? " {$options['class']}" : '';
    
    $input = '';
    if ($type === 'textarea') {
        $input = "<textarea class=\"form-control$class\" name=\"$name\" id=\"$name\" $requiredAttr rows=\"3\">$value</textarea>";
    } elseif ($type === 'select') {
        $input = "<select class=\"form-control$class\" name=\"$name\" id=\"$name\" $requiredAttr>";
        if (isset($options['options'])) {
            foreach ($options['options'] as $val => $label_text) {
                $selected = $val === $value ? 'selected' : '';
                $input .= "<option value=\"$val\" $selected>$label_text</option>";
            }
        }
        $input .= "</select>";
    } else {
        $input = "<input type=\"$type\" class=\"form-control$class\" name=\"$name\" id=\"$name\" value=\"$value\" $requiredAttr$placeholder>";
    }
    
    return <<<HTML
<div class="mb-3">
    <label for="$name" class="form-label">$label</label>
    $input
</div>
HTML;
}

/**
 * Render breadcrumbs
 */
function breadcrumbs($crumbs = []) {
    if (empty($crumbs)) return '';
    
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    foreach ($crumbs as $crumb) {
        if (isset($crumb['url'])) {
            $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($crumb['url']) . '">' . htmlspecialchars($crumb['label']) . '</a></li>';
        } else {
            $html .= '<li class="breadcrumb-item active">' . htmlspecialchars($crumb['label']) . '</li>';
        }
    }
    $html .= '</ol></nav>';
    
    return $html;
}

/**
 * Create stat card
 */
function statCard($label, $value, $icon = '', $bgColor = 'primary') {
    $iconHtml = $icon ? "<i class=\"bi bi-$icon\"></i>" : '';
    
    return <<<HTML
<div class="col-md-3 mb-3">
    <div class="card bg-$bgColor text-white">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="mb-0 text-white-50">$label</p>
                    <h3 class="mb-0">$value</h3>
                </div>
                <div style="font-size: 2rem;">$iconHtml</div>
            </div>
        </div>
    </div>
</div>
HTML;
}

/**
 * Create action buttons group
 */
function actionButtons($buttons = []) {
    $html = '<div class="action-buttons">';
    foreach ($buttons as $btn) {
        $class = $btn['class'] ?? 'btn-secondary';
        $icon = isset($btn['icon']) ? "<i class=\"bi bi-{$btn['icon']}\"></i> " : '';
        $href = $btn['href'] ?? '#';
        $onclick = isset($btn['onclick']) ? " onclick=\"{$btn['onclick']}\"" : '';
        $title = isset($btn['title']) ? " title=\"{$btn['title']}\"" : '';
        
        if (isset($btn['modal'])) {
            $html .= "<button type=\"button\" class=\"btn btn-sm $class\" data-bs-toggle=\"modal\" data-bs-target=\"#{$btn['modal']}\"$title>$icon{$btn['label']}</button>";
        } else {
            $html .= "<a href=\"$href\" class=\"btn btn-sm $class\"$title>$icon{$btn['label']}</a>";
        }
    }
    $html .= '</div>';
    
    return $html;
}
?>
