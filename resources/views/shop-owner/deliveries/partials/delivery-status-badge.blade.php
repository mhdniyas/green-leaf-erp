@include('shop-owner.components.status-badge', ['label' => $order->warehouseWorkflowLabel(), 'tone' => $order->warehouseWorkflowTone()])
