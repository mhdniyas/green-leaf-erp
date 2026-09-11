import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { CSS2DRenderer, CSS2DObject } from 'three/examples/jsm/renderers/CSS2DRenderer.js';

/**
 * Green Leaf ERP — 3D Financial Pyramid Explorer
 * 
 * 3D Tiered Pyramid Architecture:
 * - Summit / Apex (Top Tier, Y = 175): Green Leaf Main Company (Central Capstone Hub)
 * - Tier 1 (Upper Tier, Y = 90): Major Financial Branches (Banks, Shops, Purchasers, Vendors, Staff, Audit)
 * - Tier 2 (Middle Tier, Y = 5): Specific Entities (Actual Bank Accounts, Retail Stores, Active Purchasers)
 * - Tier 3 (Base Tier, Y = -75): Foundation Entities (Mandi Suppliers, Credit Settlements, Retained Holdings)
 * - Physical 3D Stepped Pyramid Platforms with soft architectural lighting and corner slope guidelines
 * - Zero emojis — clean engineering typography and precision UI
 */
export class CashFlow3DExplorer {
    constructor(container, options = {}) {
        this.container = container;
        this.nodesData = options.nodes || [];
        this.edgesData = options.edges || [];
        this.onOpenTransactions = options.onOpenTransactions || (() => {});
        this.onOpenEdgeDetails = options.onOpenEdgeDetails || (() => {});
        this.onBreadcrumbChange = options.onBreadcrumbChange || (() => {});
        this.onFallback = options.onFallback || (() => {});

        // Navigation state
        this.activePath = ['root'];
        this.focusedNodeId = 'root';

        // Pyramid Tier 1 category positions on the upper terrace (Y = 90)
        this.tier1Positions = {
            branch_banks: new THREE.Vector3(-140, 90, -110),       // Upper-left terrace
            branch_shops: new THREE.Vector3(140, 90, -110),        // Upper-right terrace
            branch_review: new THREE.Vector3(180, 90, 20),         // Mid-right terrace
            branch_purchasers: new THREE.Vector3(130, 90, 130),    // Lower-right terrace
            branch_vendors: new THREE.Vector3(-130, 90, 130),      // Lower-left terrace
            branch_employees: new THREE.Vector3(-180, 90, 20),     // Mid-left terrace
        };

        // Palette definitions (clean, high-contrast, neural-inspired pyramid colors)
        this.nodeStyles = {
            company: { color: 0x047857, ring: 0x22c55e, emissive: 0x064e3b, badge: 'Pyramid Apex (Summit)' },
            shop_group: { color: 0xeab308, ring: 0xfacc15, emissive: 0xca8a04, badge: 'Tier 1: Retail Inflows' },
            shop: { color: 0xf59e0b, ring: 0xfde047, emissive: 0xd97706, badge: 'Tier 2: Shop Node' },
            bank_group: { color: 0x0284c7, ring: 0x38bdf8, emissive: 0x0369a1, badge: 'Tier 1: Treasury Vaults' },
            bank: { color: 0x0ea5e9, ring: 0x7dd3fc, emissive: 0x0284c7, badge: 'Tier 2: Bank Node' },
            purchaser_group: { color: 0x16a34a, ring: 0x4ade80, emissive: 0x15803d, badge: 'Tier 1: Procurement' },
            purchaser: { color: 0x22c55e, ring: 0x86efac, emissive: 0x16a34a, badge: 'Tier 2: Purchaser Node' },
            vendor_group: { color: 0xea580c, ring: 0xfb923c, emissive: 0xc2410c, badge: 'Tier 1: Credit Outflows' },
            vendor: { color: 0xf97316, ring: 0xfdba74, emissive: 0xea580c, badge: 'Tier 2: Vendor Node' },
            employee_group: { color: 0x4338ca, ring: 0x818cf8, emissive: 0x3730a3, badge: 'Tier 1: Staff Payroll' },
            employee: { color: 0x4f46e5, ring: 0xa5b4fc, emissive: 0x4338ca, badge: 'Tier 2: Staff Node' },
            review_group: { color: 0xa21caf, ring: 0xe879f9, emissive: 0x86198f, badge: 'Tier 1: Reconciliation' },
            holding: { color: 0xd97706, ring: 0xfef08a, emissive: 0xb45309, badge: 'Foundation Holding' },
            other: { color: 0x64748b, ring: 0xcbd5e1, emissive: 0x475569, badge: 'Base Node' },
        };

        this.nodeObjects = new Map();
        this.branchObjects = new Map();
        this.flowParticles = [];
        this.activeDetailCard = null;

        // Camera animation (overview looking slightly down at the pyramid)
        this.cameraTargetPos = new THREE.Vector3(0, 290, 580);
        this.controlsLookAt = new THREE.Vector3(0, 50, 0);
        this.isCameraAnimating = false;

        this.initScene();
    }

    static isWebGLAvailable() {
        try {
            const canvas = document.createElement('canvas');
            return !!(window.WebGLRenderingContext && (canvas.getContext('webgl') || canvas.getContext('experimental-webgl')));
        } catch (e) {
            return false;
        }
    }

    /**
     * 1. Initialize Scene, Renderers, Camera, Lights, OrbitControls, and 3D Pyramid Pedestal.
     */
    initScene() {
        if (!CashFlow3DExplorer.isWebGLAvailable()) {
            this.onFallback('WebGL acceleration is not available on this browser.');
            return;
        }

        const width = this.container.clientWidth || 1000;
        const height = this.container.clientHeight || 750;

        // 1. Scene: Clean studio backdrop
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0xfbfcfd);

        // 2. Camera
        this.camera = new THREE.PerspectiveCamera(45, width / height, 1, 4000);
        this.camera.position.copy(this.cameraTargetPos);

        // 3. WebGL Renderer
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
        this.renderer.setSize(width, height);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.container.appendChild(this.renderer.domElement);

        // 4. CSS2D Renderer for clean HTML labels & 3D detail cards
        this.labelRenderer = new CSS2DRenderer();
        this.labelRenderer.setSize(width, height);
        this.labelRenderer.domElement.style.position = 'absolute';
        this.labelRenderer.domElement.style.top = '0px';
        this.labelRenderer.domElement.style.left = '0px';
        this.labelRenderer.domElement.style.pointerEvents = 'none';
        this.container.appendChild(this.labelRenderer.domElement);

        // 5. OrbitControls
        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.055;
        this.controls.screenSpacePanning = true;
        this.controls.minDistance = 90;
        this.controls.maxDistance = 1400;
        this.controls.maxPolarAngle = Math.PI / 2 + 0.04;
        this.controls.target.copy(this.controlsLookAt);

        // 6. Lighting
        this.setupLighting();

        // 7. Architectural 3D Stepped Pyramid Pedestal Platforms & Slope Guidelines
        this.setupPyramidArchitecture();

        // 8. Event Listeners
        this.setupEventListeners();

        // 9. Create Apex Node (Summit of Pyramid)
        this.createApexNode();

        // 10. Initial Build
        this.updateVisibility(false);

        // 11. Start 60fps Loop
        this.animate = this.animate.bind(this);
        this.animate();
    }

    setupLighting() {
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.95);
        this.scene.add(ambientLight);

        const hemiLight = new THREE.HemisphereLight(0xffffff, 0xe2e8f0, 0.5);
        hemiLight.position.set(0, 350, 0);
        this.scene.add(hemiLight);

        const keyLight = new THREE.DirectionalLight(0xffffff, 0.85);
        keyLight.position.set(220, 420, 260);
        keyLight.castShadow = true;
        keyLight.shadow.mapSize.width = 1024;
        keyLight.shadow.mapSize.height = 1024;
        keyLight.shadow.camera.near = 50;
        keyLight.shadow.camera.far = 1200;
        const d = 420;
        keyLight.shadow.camera.left = -d;
        keyLight.shadow.camera.right = d;
        keyLight.shadow.camera.top = d;
        keyLight.shadow.camera.bottom = -d;
        keyLight.shadow.bias = -0.0004;
        this.scene.add(keyLight);

        const fillLight = new THREE.DirectionalLight(0xf1f5f9, 0.4);
        fillLight.position.set(-200, 180, -200);
        this.scene.add(fillLight);
    }

    /**
     * Build physical 3D Stepped Pyramid Platforms and Ridge Guidelines.
     */
    setupPyramidArchitecture() {
        const stepMat = new THREE.MeshStandardMaterial({
            color: 0xf8fafc,
            roughness: 0.55,
            metalness: 0.05,
        });

        // 4 Stepped Pyramid Terraces
        const steps = [
            { y: -88, size: 840, height: 10 },  // Tier 3 Platform (Broad Foundation)
            { y: -8, size: 600, height: 10 },   // Tier 2 Platform (Operational Layer)
            { y: 78, size: 380, height: 10 },   // Tier 1 Platform (Main Branches)
            { y: 160, size: 150, height: 10 },  // Apex Platform (Summit Hub)
        ];

        steps.forEach(s => {
            const geo = new THREE.BoxGeometry(s.size, s.height, s.size);
            const mesh = new THREE.Mesh(geo, stepMat);
            mesh.position.y = s.y;
            mesh.receiveShadow = true;
            mesh.castShadow = true;
            this.scene.add(mesh);

            // Subtle border outline around each terrace step
            const edges = new THREE.EdgesGeometry(geo);
            const line = new THREE.LineSegments(edges, new THREE.LineBasicMaterial({ color: 0xcbd5e1 }));
            mesh.add(line);
        });

        // Pyramid Ridge Guidelines running from Apex (0, 175, 0) down the 4 corners to the base
        const apexY = 175;
        const baseY = -85;
        const baseHalf = 420;

        const corners = [
            [-baseHalf, baseY, -baseHalf],
            [baseHalf, baseY, -baseHalf],
            [baseHalf, baseY, baseHalf],
            [-baseHalf, baseY, baseHalf],
        ];

        corners.forEach(([cx, cy, cz]) => {
            const linePoints = [
                new THREE.Vector3(0, apexY, 0),
                new THREE.Vector3(cx, cy, cz)
            ];
            const lineGeo = new THREE.BufferGeometry().setFromPoints(linePoints);
            const lineMat = new THREE.LineDashedMaterial({
                color: 0x94a3b8,
                dashSize: 8,
                gapSize: 6,
                transparent: true,
                opacity: 0.45,
            });
            const ridgeLine = new THREE.Line(lineGeo, lineMat);
            ridgeLine.computeLineDistances();
            this.scene.add(ridgeLine);
        });

        // Shadow catcher floor
        const floorGeo = new THREE.PlaneGeometry(2000, 2000);
        const floorMat = new THREE.ShadowMaterial({ opacity: 0.08 });
        const floor = new THREE.Mesh(floorGeo, floorMat);
        floor.rotation.x = -Math.PI / 2;
        floor.position.y = -95;
        floor.receiveShadow = true;
        this.scene.add(floor);
    }

    setupEventListeners() {
        this.onResize = () => {
            if (!this.container) return;
            const w = this.container.clientWidth;
            const h = this.container.clientHeight;
            this.camera.aspect = w / h;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(w, h);
            this.labelRenderer.setSize(w, h);
        };
        window.addEventListener('resize', this.onResize);

        this.raycaster = new THREE.Raycaster();
        this.mouse = new THREE.Vector2();

        this.onPointerDown = (e) => {
            this.downTime = Date.now();
            this.downX = e.clientX;
            this.downY = e.clientY;
        };

        this.onPointerUp = (e) => {
            const dist = Math.hypot(e.clientX - this.downX, e.clientY - this.downY);
            if (dist > 6 || Date.now() - this.downTime > 300) return;

            const rect = this.renderer.domElement.getBoundingClientRect();
            this.mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            this.mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

            this.raycaster.setFromCamera(this.mouse, this.camera);
            const clickable = [];
            this.nodeObjects.forEach(obj => {
                clickable.push(obj.mesh);
                if (obj.disc) clickable.push(obj.disc);
            });
            this.branchObjects.forEach(obj => clickable.push(obj.mesh));

            const hits = this.raycaster.intersectObjects(clickable);
            if (hits.length > 0) {
                const hit = hits[0].object;
                if (hit.userData && hit.userData.node) {
                    this.showNodePopup(hit.userData.node);
                } else if (hit.userData && hit.userData.edge) {
                    this.onOpenEdgeDetails(hit.userData.edge);
                }
            } else {
                this.hideNodePopup();
            }
        };

        this.renderer.domElement.addEventListener('pointerdown', this.onPointerDown);
        this.renderer.domElement.addEventListener('pointerup', this.onPointerUp);
    }

    /**
     * Create Apex Capstone Node at the summit of the pyramid (Y = 175).
     */
    createApexNode() {
        const rootData = this.nodesData.find(n => n.id === 'root') || {
            id: 'root',
            label: 'Green Leaf',
            subtitle: 'Main Company',
            opening: 0,
            inflow: 0,
            outflow: 0,
            closing: 0,
            holding: 0,
        };

        const rootGroup = new THREE.Group();
        rootGroup.position.set(0, 175, 0);

        // Summit Capstone Pyramid
        const capstoneGeo = new THREE.ConeGeometry(24, 28, 4);
        const capstoneMat = new THREE.MeshStandardMaterial({
            color: 0x047857,
            emissive: 0x064e3b,
            emissiveIntensity: 0.25,
            roughness: 0.25,
            metalness: 0.2,
        });
        const capstone = new THREE.Mesh(capstoneGeo, capstoneMat);
        capstone.rotation.y = Math.PI / 4; // Align pyramid faces with platforms
        capstone.position.y = 14;
        capstone.castShadow = true;
        capstone.receiveShadow = true;
        rootGroup.add(capstone);

        // Concentric Emerald Ring around Apex
        const ringGeo = new THREE.RingGeometry(28, 32, 40);
        const ringMat = new THREE.MeshBasicMaterial({
            color: 0x22c55e,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.9,
        });
        const ring = new THREE.Mesh(ringGeo, ringMat);
        ring.rotation.x = -Math.PI / 2;
        ring.position.y = 2;
        rootGroup.add(ring);

        capstone.userData = { node: rootData };
        ring.userData = { node: rootData };

        this.scene.add(rootGroup);

        // CSS2D Apex Card
        const labelDiv = document.createElement('div');
        labelDiv.className = 'root-hub-label select-none pointer-events-auto cursor-pointer transition-all duration-200 hover:scale-105';
        labelDiv.style.transform = 'translate3d(-50%, -100%, 0)';

        labelDiv.innerHTML = `
            <div class="bg-white text-slate-900 border-2 border-emerald-600 rounded-2xl p-3 shadow-xl backdrop-blur-md min-w-[210px] text-center">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <span class="w-5 h-5 rounded-full bg-emerald-600 text-white font-bold text-[10px] flex items-center justify-center font-mono">GL</span>
                    <span class="font-extrabold text-sm tracking-tight text-slate-900 font-sans">GREEN LEAF</span>
                </div>
                <div class="text-[9px] uppercase font-bold tracking-wider text-emerald-700 mb-2 font-sans">Pyramid Apex (Summit)</div>
                <div class="grid grid-cols-2 gap-1.5 pt-2 border-t border-slate-100 text-left font-mono text-[10px]">
                    <div>
                        <span class="text-[8px] uppercase tracking-wider text-slate-400 block font-sans">Opening</span>
                        <span class="font-bold text-slate-800">₹${this.formatLakhs(rootData.opening)}</span>
                    </div>
                    <div>
                        <span class="text-[8px] uppercase tracking-wider text-emerald-600 block font-sans">Money In</span>
                        <span class="font-bold text-emerald-600">₹${this.formatLakhs(rootData.inflow)}</span>
                    </div>
                    <div>
                        <span class="text-[8px] uppercase tracking-wider text-rose-600 block font-sans">Money Out</span>
                        <span class="font-bold text-rose-600">₹${this.formatLakhs(rootData.outflow)}</span>
                    </div>
                    <div>
                        <span class="text-[8px] uppercase tracking-wider text-slate-600 block font-sans">Closing</span>
                        <span class="font-black text-slate-900">₹${this.formatLakhs(rootData.closing)}</span>
                    </div>
                </div>
            </div>
        `;

        labelDiv.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showNodePopup(rootData);
        });

        const labelObj = new CSS2DObject(labelDiv);
        labelObj.position.set(0, 48, 0);
        this.scene.add(labelObj);

        this.nodeObjects.set('root', {
            group: rootGroup,
            mesh: capstone,
            disc: ring,
            label: labelObj,
            data: rootData,
            position: new THREE.Vector3(0, 175, 0),
        });
    }

    /**
     * Compute deterministic 3D coordinates based on Pyramid Tiers.
     */
    layoutChildren() {
        const positions = new Map();
        positions.set('root', new THREE.Vector3(0, 175, 0));

        // Tier 1: Categories positioned on Upper Terrace (Y = 90)
        const level1Nodes = this.nodesData.filter(n => n.parent_id === 'root');
        level1Nodes.forEach(cat => {
            const p = this.tier1Positions[cat.id] || new THREE.Vector3(0, 90, 140);
            positions.set(cat.id, p.clone());
        });

        // Tier 2: Children on Middle Terrace (Y = 5) radiating from parent's position
        const activeCatId = this.activePath[1];
        if (activeCatId && positions.has(activeCatId)) {
            const catPos = positions.get(activeCatId);
            const children = this.nodesData.filter(n => n.parent_id === activeCatId);
            const count = children.length;
            const isLarge = count > 8;

            children.forEach((child, i) => {
                // Spread outward along the slope towards the edge of the Tier 2 platform
                const span = Math.min(1.4, 0.18 * count);
                const offset = count > 1 ? -span / 2 + (i / (count - 1)) * span : 0;
                const angle = Math.atan2(catPos.z, catPos.x) + offset;

                let dist = 145;
                if (isLarge) {
                    const tier = (i % 2 === 0 ? 1 : 2);
                    dist = tier === 1 ? 120 : 185;
                }

                const childPos = new THREE.Vector3(
                    catPos.x + Math.cos(angle) * dist,
                    5, // Tier 2 elevation
                    catPos.z + Math.sin(angle) * dist
                );
                positions.set(child.id, childPos);
            });
        }

        // Tier 3: Grandchildren & Retained Holding on Base Terrace (Y = -75)
        const activeChildId = this.activePath[2];
        if (activeChildId && positions.has(activeChildId)) {
            const childPos = positions.get(activeChildId);
            const grandChildren = this.nodesData.filter(n => n.parent_id === activeChildId);
            const gcCount = grandChildren.length;

            grandChildren.forEach((gc, idx) => {
                const gcSpan = Math.min(1.2, 0.22 * gcCount);
                const angleOffset = gcCount > 1 ? -gcSpan / 2 + (idx / (gcCount - 1)) * gcSpan : 0;
                const baseAngle = Math.atan2(childPos.z, childPos.x) + angleOffset;
                const dist = 115;

                positions.set(gc.id, new THREE.Vector3(
                    childPos.x + Math.cos(baseAngle) * dist,
                    -75, // Tier 3 elevation
                    childPos.z + Math.sin(baseAngle) * dist
                ));
            });

            // Holding node on Tier 3
            const childData = this.nodesData.find(n => n.id === activeChildId);
            if (childData && childData.holding > 0.001) {
                const holdingId = activeChildId + '_holding';
                const holdAngle = Math.atan2(childPos.z, childPos.x) + 0.7;
                positions.set(holdingId, new THREE.Vector3(
                    childPos.x + Math.cos(holdAngle) * 95,
                    -75,
                    childPos.z + Math.sin(holdAngle) * 95
                ));
            }
        }

        return positions;
    }

    /**
     * Rebuild visible hierarchy based on activePath (Pyramid drilldown).
     */
    updateVisibility(animate = true) {
        const positions = this.layoutChildren();

        const targetNodeIds = new Set(['root']);
        const level1Nodes = this.nodesData.filter(n => n.parent_id === 'root');
        level1Nodes.forEach(cat => {
            targetNodeIds.add(cat.id);

            if (this.activePath.includes(cat.id)) {
                const children = this.nodesData.filter(n => n.parent_id === cat.id);
                children.forEach(c => {
                    targetNodeIds.add(c.id);

                    if (this.activePath.includes(c.id)) {
                        const grandChildren = this.nodesData.filter(n => n.parent_id === c.id);
                        grandChildren.forEach(gc => targetNodeIds.add(gc.id));

                        if (c.holding > 0.001) {
                            targetNodeIds.add(c.id + '_holding');
                        }
                    }
                });
            }
        });

        // Dispose hidden nodes
        const toRemove = [];
        this.nodeObjects.forEach((_, id) => {
            if (id !== 'root' && !targetNodeIds.has(id)) {
                toRemove.push(id);
            }
        });
        toRemove.forEach(id => this.disposeNode(id));

        // Create or reposition visible nodes
        targetNodeIds.forEach(id => {
            if (id === 'root') return;
            const pos = positions.get(id);
            if (!pos) return;

            let nodeData = this.nodesData.find(n => n.id === id);

            if (!nodeData && id.endsWith('_holding')) {
                const parentNodeId = id.replace('_holding', '');
                const parentNode = this.nodesData.find(n => n.id === parentNodeId);
                nodeData = {
                    id,
                    parent_id: parentNodeId,
                    label: `Holding (${parentNode ? parentNode.label : 'Balance'})`,
                    type: 'holding',
                    holding: parentNode ? parentNode.holding : 0,
                    opening: 0,
                    inflow: 0,
                    outflow: 0,
                    closing: parentNode ? parentNode.holding : 0,
                    movements_count: 0,
                    child_count: 0,
                };
            }

            if (nodeData) {
                if (!this.nodeObjects.has(id)) {
                    this.createEntityNode(nodeData, pos, animate);
                } else {
                    const obj = this.nodeObjects.get(id);
                    obj.position.copy(pos);
                    obj.group.position.copy(pos);
                    obj.label.position.set(pos.x, pos.y + 16, pos.z);
                }
            }
        });

        // Resolve active branch connections along pyramid slopes
        const targetEdges = this.resolveActiveBranches(targetNodeIds);

        const branchesToRemove = [];
        this.branchObjects.forEach((_, edgeId) => {
            if (!targetEdges.some(e => e.id === edgeId)) {
                branchesToRemove.push(edgeId);
            }
        });
        branchesToRemove.forEach(edgeId => this.disposeBranch(edgeId));

        targetEdges.forEach(edge => {
            const fromPos = positions.get(edge.from);
            const toPos = positions.get(edge.to);
            if (fromPos && toPos) {
                if (!this.branchObjects.has(edge.id)) {
                    this.createRelationBranch(edge, fromPos, toPos, animate);
                }
            }
        });

        this.updateFocusOpacity();
        this.createFlowParticles();
        this.emitBreadcrumb();
    }

    resolveActiveBranches(visibleNodeIds) {
        const edges = [];

        // 1. Apex to Tier 1 Categories
        const level1Nodes = this.nodesData.filter(n => n.parent_id === 'root');
        level1Nodes.forEach(cat => {
            const edge = this.edgesData.find(e => (e.from === 'root' && e.to === cat.id) || (e.to === cat.id));
            const amount = edge ? edge.amount : (cat.inflow + cat.outflow);
            const count = edge ? edge.movement_count : (cat.movements_count || 1);

            edges.push({
                id: `branch_root_${cat.id}`,
                from: 'root',
                to: cat.id,
                from_label: 'Green Leaf (Apex)',
                to_label: cat.label,
                amount: Math.round(amount),
                formatted_amount: this.formatLakhs(amount),
                movement_count: count,
                movement_type: edge ? edge.movement_type : 'category_flow',
                direction: 'forward',
                level: 1,
            });
        });

        // 2. Tier 1 to Tier 2 Children
        const activeCatId = this.activePath[1];
        if (activeCatId) {
            const children = this.nodesData.filter(n => n.parent_id === activeCatId);
            children.forEach(c => {
                const edge = this.edgesData.find(e => (e.from === activeCatId && e.to === c.id) || (e.to === c.id) || (e.from === c.id));
                const amount = edge ? edge.amount : (c.inflow + c.outflow);
                const count = edge ? edge.movement_count : (c.movements_count || 1);

                edges.push({
                    id: `branch_${activeCatId}_${c.id}`,
                    from: activeCatId,
                    to: c.id,
                    from_label: this.nodesData.find(n => n.id === activeCatId)?.label || activeCatId,
                    to_label: c.label,
                    amount: Math.round(amount),
                    formatted_amount: this.formatLakhs(amount),
                    movement_count: count,
                    movement_type: edge ? edge.movement_type : 'child_flow',
                    direction: edge ? edge.direction : 'forward',
                    level: 2,
                });
            });
        }

        // 3. Tier 2 to Tier 3 Grandchildren & Holding
        const activeChildId = this.activePath[2];
        if (activeChildId) {
            const grandChildren = this.nodesData.filter(n => n.parent_id === activeChildId);
            grandChildren.forEach(gc => {
                const gcEdge = this.edgesData.find(e => (e.from === activeChildId && e.to === gc.id) || (e.to === gc.id));
                const amount = gcEdge ? gcEdge.amount : (gc.inflow + gc.outflow);

                edges.push({
                    id: `branch_${activeChildId}_${gc.id}`,
                    from: activeChildId,
                    to: gc.id,
                    from_label: this.nodesData.find(n => n.id === activeChildId)?.label || activeChildId,
                    to_label: gc.label,
                    amount: Math.round(amount),
                    formatted_amount: this.formatLakhs(amount),
                    movement_count: gc.movements_count || 1,
                    movement_type: gcEdge ? gcEdge.movement_type : 'grandchild_flow',
                    direction: 'forward',
                    level: 3,
                });
            });

            const childData = this.nodesData.find(n => n.id === activeChildId);
            if (childData && childData.holding > 0.001) {
                edges.push({
                    id: `branch_${activeChildId}_holding`,
                    from: activeChildId,
                    to: activeChildId + '_holding',
                    from_label: childData.label,
                    to_label: 'Retained Holding',
                    amount: Math.round(childData.holding),
                    formatted_amount: this.formatLakhs(childData.holding),
                    movement_count: 1,
                    movement_type: 'holding',
                    direction: 'return',
                    level: 3,
                });
            }
        }

        return edges;
    }

    createEntityNode(node, pos, animate = true) {
        const style = this.nodeStyles[node.type] || this.nodeStyles.other;
        const isBranchCategory = node.parent_id === 'root';
        const isHolding = node.type === 'holding';
        const radius = isBranchCategory ? 15 : (isHolding ? 9 : 11);

        const group = new THREE.Group();
        group.position.copy(pos);

        // Circular 3D Node Sphere
        const sphereGeo = new THREE.SphereGeometry(radius, 32, 24);
        const sphereMat = new THREE.MeshStandardMaterial({
            color: style.color,
            emissive: style.emissive,
            emissiveIntensity: 0.2,
            roughness: 0.3,
            metalness: 0.15,
            transparent: true,
            opacity: 1.0,
        });
        const sphere = new THREE.Mesh(sphereGeo, sphereMat);
        sphere.castShadow = true;
        sphere.receiveShadow = true;
        group.add(sphere);

        // Concentric Outline Ring
        const ringGeo = new THREE.RingGeometry(radius * 1.1, radius * 1.28, 40);
        const ringMat = new THREE.MeshBasicMaterial({
            color: style.ring,
            side: THREE.DoubleSide,
            transparent: true,
            opacity: 0.85,
        });
        const ring = new THREE.Mesh(ringGeo, ringMat);
        ring.rotation.x = -Math.PI / 2;
        ring.position.y = 1.5;
        group.add(ring);

        sphere.userData = { node };
        ring.userData = { node };

        if (animate) {
            group.scale.set(0.2, 0.2, 0.2);
            this.animateNodeAppear(group, 1.0, 450);
        }

        this.scene.add(group);

        const labelObj = this.createNodeLabel(node, pos, isBranchCategory, isHolding, style);
        this.scene.add(labelObj);

        this.nodeObjects.set(node.id, {
            group,
            mesh: sphere,
            disc: ring,
            label: labelObj,
            data: node,
            position: pos.clone(),
            isBranchCategory,
            isHolding,
        });
    }

    createNodeLabel(node, pos, isBranchCategory, isHolding, style) {
        const labelDiv = document.createElement('div');
        labelDiv.className = 'node-explorer-label select-none pointer-events-auto cursor-pointer transition-all duration-200 hover:scale-105';
        labelDiv.style.transform = 'translate3d(-50%, -100%, 0)';

        const isCurrentFocus = this.focusedNodeId === node.id;
        const hasChildren = (node.child_count || 0) > 0 || this.nodesData.some(n => n.parent_id === node.id);

        let metricDisplay = '';
        if (isHolding) {
            metricDisplay = `<span class="text-amber-600 font-mono font-bold text-xs">₹${this.formatLakhs(node.holding)}</span>`;
        } else if (isBranchCategory) {
            metricDisplay = `<span class="text-slate-800 font-mono font-bold text-xs">₹${this.formatLakhs(node.inflow + node.outflow)}</span>`;
        } else if (node.type === 'vendor') {
            metricDisplay = `<span class="text-orange-600 font-mono font-bold text-xs">₹${this.formatLakhs(node.outflow || node.holding)}</span>`;
        } else if (node.holding > 0.001) {
            metricDisplay = `<span class="text-green-700 font-mono font-bold text-xs">₹${this.formatLakhs(node.holding)} hold</span>`;
        }

        const countBadge = isBranchCategory && node.child_count > 0 ? `${node.child_count} entities` : '';

        const cardBg = isCurrentFocus
            ? 'bg-white text-slate-900 border-2 border-slate-900 shadow-xl ring-2 ring-slate-900/20'
            : 'bg-white/95 text-slate-800 border border-slate-200 shadow-md hover:border-slate-400 hover:shadow-lg';

        labelDiv.innerHTML = `
            <div class="${cardBg} px-2.5 py-1.5 rounded-xl text-center backdrop-blur-md min-w-[105px] max-w-[175px]">
                <div class="flex items-center justify-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full" style="background-color: #${style.color.toString(16).padStart(6, '0')}"></span>
                    <span class="font-extrabold text-xs tracking-tight text-slate-900 truncate font-sans">${node.label}</span>
                </div>
                ${metricDisplay ? `<div class="mt-0.5">${metricDisplay}</div>` : ''}
                ${countBadge ? `<div class="text-[9px] text-slate-500 font-sans mt-0.5 font-medium">${countBadge}</div>` : ''}
            </div>
        `;

        labelDiv.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showNodePopup(node);
        });

        labelDiv.addEventListener('dblclick', (e) => {
            e.stopPropagation();
            if (hasChildren) {
                this.exploreNode(node.id);
            } else {
                this.openTransactions(node);
            }
        });

        const labelObj = new CSS2DObject(labelDiv);
        labelObj.position.set(pos.x, pos.y + (isBranchCategory ? 18 : 14), pos.z);
        return labelObj;
    }

    createRelationBranch(edge, fromPos, toPos, animate = true) {
        // Curve along the slope of the pyramid
        const midPoint = new THREE.Vector3().addVectors(fromPos, toPos).multiplyScalar(0.5);
        const dist = fromPos.distanceTo(toPos);
        const elevation = Math.min(45, Math.max(12, dist * 0.14));
        midPoint.y += elevation;

        const dir = new THREE.Vector3().subVectors(toPos, fromPos).normalize();
        const perp = new THREE.Vector3(-dir.z, 0, dir.x).multiplyScalar(elevation * 0.22);
        midPoint.add(perp);

        const curve = new THREE.CatmullRomCurve3([
            fromPos.clone(),
            new THREE.Vector3().lerpVectors(fromPos, midPoint, 0.45).add(new THREE.Vector3(0, elevation * 0.2, 0)),
            midPoint,
            new THREE.Vector3().lerpVectors(midPoint, toPos, 0.55).add(new THREE.Vector3(0, elevation * 0.1, 0)),
            toPos.clone(),
        ]);

        const radius = Math.min(3.2, Math.max(0.9, Math.log10((edge.amount || 1000) + 1) * 0.6));
        const isReturn = edge.direction === 'return' || edge.movement_type === 'unexplained_difference';
        const branchColor = isReturn ? 0xe11d48 : (edge.movement_type === 'holding' ? 0xd97706 : 0x475569);

        const tubeMat = new THREE.MeshStandardMaterial({
            color: branchColor,
            emissive: branchColor,
            emissiveIntensity: 0.1,
            roughness: 0.4,
            transparent: true,
            opacity: 0.75,
        });

        let tubeGeo;
        if (animate) {
            tubeGeo = this.buildSubCurveGeometry(curve, 0.08, radius);
        } else {
            tubeGeo = new THREE.TubeGeometry(curve, 28, radius, 8, false);
        }

        const tubeMesh = new THREE.Mesh(tubeGeo, tubeMat);
        tubeMesh.castShadow = true;
        tubeMesh.userData = { edge, curve, radius, branchColor };
        this.scene.add(tubeMesh);

        const labelObj = this.createAmountLabel(edge, curve);
        this.scene.add(labelObj);

        const record = {
            id: edge.id,
            mesh: tubeMesh,
            label: labelObj,
            curve,
            edge,
            radius,
            startNodeId: edge.from,
            endNodeId: edge.to,
        };

        this.branchObjects.set(edge.id, record);

        if (animate) {
            this.animateBranchGrowth(record, 450);
        }
    }

    createAmountLabel(edge, curve) {
        const midPoint = curve.getPoint(0.5);
        const labelDiv = document.createElement('div');
        labelDiv.className = 'branch-flow-badge select-none pointer-events-auto cursor-pointer transition-transform duration-150 hover:scale-110';
        labelDiv.style.transform = 'translate3d(-50%, -50%, 0)';

        const isReturn = edge.direction === 'return';
        const borderClass = isReturn
            ? 'border-rose-300 text-rose-700'
            : (edge.movement_type === 'holding' ? 'border-amber-400 text-amber-800' : 'border-slate-300 text-slate-700');

        labelDiv.innerHTML = `
            <div class="bg-white/95 ${borderClass} px-2 py-0.5 rounded-full text-[10px] font-mono font-bold border shadow-md flex items-center gap-1"
                 title="${edge.from_label} → ${edge.to_label}: ₹${Number(edge.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})} (${edge.movement_count} moves)">
                <span>${edge.formatted_amount || ('₹' + this.formatLakhs(edge.amount))}</span>
            </div>
        `;

        labelDiv.addEventListener('click', (e) => {
            e.stopPropagation();
            this.onOpenEdgeDetails(edge);
        });

        const labelObj = new CSS2DObject(labelDiv);
        labelObj.position.copy(midPoint);
        return labelObj;
    }

    animateBranchGrowth(branchRecord, duration = 450) {
        const startTime = performance.now();
        const { mesh, curve, radius } = branchRecord;

        const step = (now) => {
            const elapsed = now - startTime;
            const progress = Math.min(1.0, elapsed / duration);

            if (mesh.geometry) mesh.geometry.dispose();
            mesh.geometry = this.buildSubCurveGeometry(curve, progress, radius);

            if (progress < 1.0) {
                requestAnimationFrame(step);
            }
        };
        requestAnimationFrame(step);
    }

    buildSubCurveGeometry(fullCurve, progress, radius) {
        const p = Math.max(0.08, progress);
        const numPoints = Math.max(4, Math.floor(28 * p));
        const subPoints = [];
        for (let i = 0; i <= numPoints; i++) {
            subPoints.push(fullCurve.getPoint((i / numPoints) * p));
        }
        const subCurve = new THREE.CatmullRomCurve3(subPoints);
        return new THREE.TubeGeometry(subCurve, 24, radius, 8, false);
    }

    animateNodeAppear(group, targetScale = 1.0, duration = 400) {
        const startTime = performance.now();
        const startScale = group.scale.x;

        const step = (now) => {
            const elapsed = now - startTime;
            const p = Math.min(1.0, elapsed / duration);
            const ease = 1 - Math.pow(1 - p, 3);
            const s = startScale + (targetScale - startScale) * ease;
            group.scale.set(s, s, s);

            if (p < 1.0) {
                requestAnimationFrame(step);
            }
        };
        requestAnimationFrame(step);
    }

    showNodePopup(node) {
        this.hideNodePopup();

        const nodeObj = this.nodeObjects.get(node.id);
        if (!nodeObj) return;

        const pos = nodeObj.position;
        const hasChildren = (node.child_count || 0) > 0 || this.nodesData.some(n => n.parent_id === node.id);
        const isCurrentlyExplored = this.activePath[this.activePath.length - 1] === node.id;

        const cardDiv = document.createElement('div');
        cardDiv.className = 'node-detail-card select-none pointer-events-auto transition-all duration-200 animate-in fade-in zoom-in-95';
        cardDiv.style.transform = 'translate3d(-50%, -100%, 0)';

        cardDiv.innerHTML = `
            <div class="bg-white text-slate-900 border border-slate-300 rounded-2xl p-4 shadow-2xl backdrop-blur-md min-w-[220px] max-w-[280px]">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <div class="font-extrabold text-sm tracking-tight text-slate-900 font-sans truncate">${node.label}</div>
                        <div class="text-[9px] uppercase font-bold text-slate-500 tracking-wider font-sans">${node.badge || node.type || 'Pyramid Node'}</div>
                    </div>
                    <button type="button" class="btn-close text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-100 transition font-mono font-bold text-xs">Close</button>
                </div>

                <div class="grid grid-cols-2 gap-2 py-2 border-y border-slate-100 text-[10px] font-mono mb-3">
                    <div>
                        <span class="text-[8px] uppercase tracking-wider text-slate-400 block font-sans">Received</span>
                        <span class="font-bold text-emerald-600">+₹${this.formatLakhs(node.inflow)}</span>
                    </div>
                    <div>
                        <span class="text-[8px] uppercase tracking-wider text-slate-400 block font-sans">Spent</span>
                        <span class="font-bold text-rose-600">-₹${this.formatLakhs(node.outflow)}</span>
                    </div>
                    <div>
                        <span class="text-[8px] uppercase tracking-wider text-slate-400 block font-sans">Holding</span>
                        <span class="font-black text-amber-700">₹${this.formatLakhs(node.holding)}</span>
                    </div>
                    <div>
                        <span class="text-[8px] uppercase tracking-wider text-slate-400 block font-sans">Movements</span>
                        <span class="font-bold text-slate-600">${node.movements_count || 0} moves</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    ${hasChildren && !isCurrentlyExplored ? `
                        <button type="button" class="btn-explore flex-1 py-1.5 px-3 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold font-sans shadow transition text-center">
                            Explore
                        </button>
                    ` : ''}
                    ${node.movements_count > 0 ? `
                        <button type="button" class="btn-tx flex-1 py-1.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold font-sans border border-slate-300 transition text-center">
                            Transactions
                        </button>
                    ` : ''}
                </div>
            </div>
        `;

        cardDiv.querySelector('.btn-close').addEventListener('click', (e) => {
            e.stopPropagation();
            this.hideNodePopup();
        });

        const btnExplore = cardDiv.querySelector('.btn-explore');
        if (btnExplore) {
            btnExplore.addEventListener('click', (e) => {
                e.stopPropagation();
                this.hideNodePopup();
                this.exploreNode(node.id);
            });
        }

        const btnTx = cardDiv.querySelector('.btn-tx');
        if (btnTx) {
            btnTx.addEventListener('click', (e) => {
                e.stopPropagation();
                this.openTransactions(node);
            });
        }

        const cardObj = new CSS2DObject(cardDiv);
        cardObj.position.set(pos.x, pos.y + 42, pos.z);
        this.scene.add(cardObj);
        this.activeDetailCard = cardObj;
    }

    hideNodePopup() {
        if (this.activeDetailCard) {
            this.scene.remove(this.activeDetailCard);
            if (this.activeDetailCard.element && this.activeDetailCard.element.parentNode) {
                this.activeDetailCard.element.parentNode.removeChild(this.activeDetailCard.element);
            }
            this.activeDetailCard = null;
        }
    }

    exploreNode(nodeId) {
        if (nodeId === 'root') {
            this.resetToApex();
            return;
        }

        const node = this.nodesData.find(n => n.id === nodeId);
        if (!node) return;

        const isCategory = node.parent_id === 'root';
        if (isCategory) {
            this.activePath = ['root', nodeId];
        } else {
            const path = ['root'];
            if (node.parent_id && node.parent_id !== 'root') {
                path.push(node.parent_id);
            }
            path.push(nodeId);
            this.activePath = Array.from(new Set(path));
        }

        this.focusedNodeId = nodeId;
        this.updateVisibility(true);
        this.focusCameraOnNode(nodeId);
    }

    goBack() {
        this.hideNodePopup();
        if (this.activePath.length <= 1) {
            return;
        }

        this.activePath.pop();
        const prevNodeId = this.activePath[this.activePath.length - 1];
        this.focusedNodeId = prevNodeId;
        this.updateVisibility(true);
        this.focusCameraOnNode(prevNodeId);
    }

    resetToApex() {
        this.hideNodePopup();
        this.activePath = ['root'];
        this.focusedNodeId = 'root';
        this.updateVisibility(true);
        this.focusCameraOnNode('root');
    }

    focusCameraOnNode(nodeId) {
        if (nodeId === 'root') {
            this.cameraTargetPos.set(0, 290, 580);
            this.controlsLookAt.set(0, 50, 0);
            this.isCameraAnimating = true;
            return;
        }

        const nodeObj = this.nodeObjects.get(nodeId);
        if (!nodeObj) return;

        const pos = nodeObj.position;
        const dir = pos.clone().normalize();

        this.cameraTargetPos.set(
            pos.x * 0.75 + dir.x * 130,
            pos.y + 110,
            pos.z * 0.75 + 320
        );
        this.controlsLookAt.set(pos.x * 0.6, pos.y + 10, pos.z * 0.6);
        this.isCameraAnimating = true;
    }

    updateFocusOpacity() {
        const isRootOnly = this.activePath.length === 1;
        const activeCatId = this.activePath[1];
        const activeChildId = this.activePath[2];

        this.nodeObjects.forEach((obj, id) => {
            if (id === 'root') {
                const opacity = isRootOnly ? 1.0 : 0.7;
                obj.mesh.material.opacity = opacity;
                obj.label.element.style.opacity = String(opacity);
                return;
            }

            let isHighlighted = false;
            if (isRootOnly) {
                isHighlighted = true;
            } else if (id === activeCatId) {
                isHighlighted = true;
            } else if (this.activePath.includes(id)) {
                isHighlighted = true;
            } else if (obj.data.parent_id === activeCatId && !activeChildId) {
                isHighlighted = true;
            } else if (id === activeChildId || obj.data.parent_id === activeChildId || id.startsWith(activeChildId)) {
                isHighlighted = true;
            }

            if (isHighlighted) {
                obj.mesh.material.opacity = 1.0;
                obj.label.element.style.opacity = '1.0';
                obj.label.element.style.filter = 'none';
            } else {
                obj.mesh.material.opacity = 0.18;
                obj.label.element.style.opacity = '0.24';
                obj.label.element.style.filter = 'grayscale(60%)';
            }
        });

        this.branchObjects.forEach((obj) => {
            let isHighlighted = false;
            if (isRootOnly) {
                isHighlighted = true;
            } else if (obj.endNodeId === activeCatId || obj.startNodeId === activeCatId) {
                isHighlighted = true;
            } else if (this.activePath.includes(obj.endNodeId) || this.activePath.includes(obj.startNodeId)) {
                isHighlighted = true;
            }

            if (isHighlighted) {
                obj.mesh.material.opacity = 0.82;
                obj.label.element.style.opacity = '1.0';
            } else {
                obj.mesh.material.opacity = 0.14;
                obj.label.element.style.opacity = '0.2';
            }
        });
    }

    createFlowParticles() {
        this.flowParticles.forEach(p => {
            this.scene.remove(p.mesh);
            if (p.mesh.geometry) p.mesh.geometry.dispose();
            if (p.mesh.material) p.mesh.material.dispose();
        });
        this.flowParticles = [];

        const isRootOnly = this.activePath.length === 1;
        const activeCatId = this.activePath[1];

        this.branchObjects.forEach(obj => {
            const isRelevant = isRootOnly || (obj.endNodeId === activeCatId || obj.startNodeId === activeCatId || this.activePath.includes(obj.endNodeId));
            if (!isRelevant) return;

            const particleGeo = new THREE.SphereGeometry(obj.radius * 1.35, 8, 8);
            const particleMat = new THREE.MeshBasicMaterial({
                color: 0x0284c7,
                transparent: true,
                opacity: 0.95,
            });
            const pMesh = new THREE.Mesh(particleGeo, particleMat);
            this.scene.add(pMesh);

            const isReturn = obj.edge.direction === 'return';

            this.flowParticles.push({
                mesh: pMesh,
                curve: obj.curve,
                speed: 0.00035,
                offset: Math.random(),
                isReturn,
            });
        });
    }

    openTransactions(node) {
        this.onOpenTransactions(node);
    }

    emitBreadcrumb() {
        const crumbs = this.activePath.map(id => {
            const n = this.nodesData.find(item => item.id === id);
            return {
                id,
                label: n ? n.label : (id === 'root' ? 'Green Leaf (Apex)' : id),
            };
        });
        this.onBreadcrumbChange(crumbs);
    }

    zoomIn() {
        this.controls.dollyIn(1.25);
        this.controls.update();
    }

    zoomOut() {
        this.controls.dollyOut(1.25);
        this.controls.update();
    }

    resetCamera() {
        this.focusCameraOnNode(this.focusedNodeId || 'root');
    }

    highlightSearch(query) {
        const q = (query || '').toLowerCase().trim();
        this.nodeObjects.forEach((obj, id) => {
            if (id === 'root') return;
            const node = obj.data;
            const card = obj.label.element.querySelector('.node-explorer-label > div');
            if (!card) return;

            if (!q) {
                card.classList.remove('ring-4', 'ring-amber-400', 'animate-pulse');
            } else if (node.label.toLowerCase().includes(q) || (node.subtitle && node.subtitle.toLowerCase().includes(q))) {
                card.classList.add('ring-4', 'ring-amber-400', 'animate-pulse');
            } else {
                card.classList.remove('ring-4', 'ring-amber-400', 'animate-pulse');
            }
        });
    }

    formatLakhs(amt) {
        const abs = Math.abs(amt || 0);
        if (abs >= 10000000) return (amt / 10000000).toFixed(2) + 'Cr';
        if (abs >= 100000) return (amt / 100000).toFixed(2) + 'L';
        if (abs >= 1000) return (amt / 1000).toFixed(1) + 'K';
        return Number(amt || 0).toFixed(2);
    }

    animate(time) {
        this.animationFrameId = requestAnimationFrame(this.animate);

        if (this.isCameraAnimating) {
            this.camera.position.lerp(this.cameraTargetPos, 0.075);
            this.controls.target.lerp(this.controlsLookAt, 0.075);

            if (this.camera.position.distanceTo(this.cameraTargetPos) < 1.0 && this.controls.target.distanceTo(this.controlsLookAt) < 0.5) {
                this.camera.position.copy(this.cameraTargetPos);
                this.controls.target.copy(this.controlsLookAt);
                this.isCameraAnimating = false;
            }
        }

        this.controls.update();

        const now = time || performance.now();
        this.flowParticles.forEach(p => {
            const rawT = (now * p.speed + p.offset) % 1.0;
            const t = p.isReturn ? (1.0 - rawT) : rawT;
            const pt = p.curve.getPoint(t);
            p.mesh.position.copy(pt);
        });

        this.renderer.render(this.scene, this.camera);
        this.labelRenderer.render(this.scene, this.camera);
    }

    disposeNode(id) {
        const obj = this.nodeObjects.get(id);
        if (!obj) return;

        this.scene.remove(obj.group);
        obj.group.traverse(child => {
            if (child.geometry) child.geometry.dispose();
            if (child.material) {
                if (Array.isArray(child.material)) child.material.forEach(m => m.dispose());
                else child.material.dispose();
            }
        });

        this.scene.remove(obj.label);
        if (obj.label.element && obj.label.element.parentNode) {
            obj.label.element.parentNode.removeChild(obj.label.element);
        }

        this.nodeObjects.delete(id);
    }

    disposeBranch(id) {
        const obj = this.branchObjects.get(id);
        if (!obj) return;

        this.scene.remove(obj.mesh);
        if (obj.mesh.geometry) obj.mesh.geometry.dispose();
        if (obj.mesh.material) obj.mesh.material.dispose();

        this.scene.remove(obj.label);
        if (obj.label.element && obj.label.element.parentNode) {
            obj.label.element.parentNode.removeChild(obj.label.element);
        }

        this.branchObjects.delete(id);
    }

    destroy() {
        if (this.animationFrameId) cancelAnimationFrame(this.animationFrameId);
        window.removeEventListener('resize', this.onResize);

        if (this.renderer && this.renderer.domElement) {
            this.renderer.domElement.removeEventListener('pointerdown', this.onPointerDown);
            this.renderer.domElement.removeEventListener('pointerup', this.onPointerUp);
        }

        this.hideNodePopup();
        this.nodeObjects.forEach((_, id) => this.disposeNode(id));
        this.branchObjects.forEach((_, id) => this.disposeBranch(id));

        if (this.controls) this.controls.dispose();
        if (this.renderer) this.renderer.dispose();
    }
}

window.CashFlow3DExplorer = CashFlow3DExplorer;
window.dispatchEvent(new CustomEvent('cashflow-explorer-ready'));
