document.addEventListener('DOMContentLoaded', () => {
    const role = window.MedLineInventoryContext?.role || 'Clinic Staff';

    const state = {
        inventory: [],
        riskData: [],
        clinics: [],
        items: [],
        selectedClinicId: ''
    };

    const elements = {
        feedback: document.querySelector('#inventory-feedback'),
        tableBody: document.querySelector('#inventory-table-body'),
        empty: document.querySelector('#inventory-empty'),
        count: document.querySelector('#inventory-count'),
        search: document.querySelector('#inventory-search'),
        riskFilter: document.querySelector('#inventory-risk-filter'),
        clinicFilter: document.querySelector('#inventory-clinic-filter'),
        refreshButton: document.querySelector('#refresh-inventory-button'),
        detailsModal: document.querySelector('#inventory-details-modal'),
        detailsContent: document.querySelector('#inventory-details-content'),
        statTotal: document.querySelector('#stat-total'),
        statCritical: document.querySelector('#stat-critical'),
        statHigh: document.querySelector('#stat-high'),
        statMedium: document.querySelector('#stat-medium'),
        statLow: document.querySelector('#stat-low'),
        addItemModal: document.querySelector('#add-item-modal'),
        addItemForm: document.querySelector('#add-item-form'),
        addItemError: document.querySelector('#add-item-error'),
        itemName: document.querySelector('#item-name'),
        itemCategory: document.querySelector('#item-category'),
        itemCriticality: document.querySelector('#item-criticality'),
        itemColdChain: document.querySelector('#item-cold-chain'),
        itemTempFields: document.querySelector('#item-temperature-fields'),
        itemMinTemp: document.querySelector('#item-min-temp'),
        itemMaxTemp: document.querySelector('#item-max-temp'),
        saveItemButton: document.querySelector('#save-item-button'),
        addStockModal: document.querySelector('#add-stock-modal'),
        addStockForm: document.querySelector('#add-stock-form'),
        addStockError: document.querySelector('#add-stock-error'),
        stockClinicId: document.querySelector('#stock-clinic-id'),
        stockItemId: document.querySelector('#stock-item-id'),
        stockQuantity: document.querySelector('#stock-quantity'),
        saveStockButton: document.querySelector('#save-stock-button'),
        recordUsageModal: document.querySelector('#record-usage-modal'),
        recordUsageForm: document.querySelector('#record-usage-form'),
        recordUsageError: document.querySelector('#record-usage-error'),
        usageItemId: document.querySelector('#usage-item-id'),
        usageStockInfo: document.querySelector('#usage-stock-info'),
        usageAvailableQty: document.querySelector('#usage-available-qty'),
        usageQuantity: document.querySelector('#usage-quantity'),
        saveUsageButton: document.querySelector('#save-usage-button')
    };

    const detailsModal = new bootstrap.Modal(elements.detailsModal);
    const addItemModal = elements.addItemModal ? new bootstrap.Modal(elements.addItemModal) : null;
    const addStockModal = elements.addStockModal ? new bootstrap.Modal(elements.addStockModal) : null;
    const recordUsageModal = elements.recordUsageModal ? new bootstrap.Modal(elements.recordUsageModal) : null;

    function displayValue(value) {
        if (value === null || value === undefined || String(value).trim() === '') {
            return 'Not provided';
        }

        return String(value);
    }

    function formatNumber(value) {
        if (value === null || value === undefined || value === '') {
            return 'Not provided';
        }

        const numericValue = Number(value);

        if (Number.isFinite(numericValue)) {
            return numericValue.toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        }

        return displayValue(value);
    }

    function riskBadgeClass(risk) {
        const normalizedRisk = risk || 'Unknown';

        return {
            Critical: 'badge-soft-danger',
            High: 'badge-soft-warning',
            Medium: 'badge-soft-info',
            Low: 'badge-soft-success',
            Unknown: 'badge-soft-info'
        }[normalizedRisk] || 'badge-soft-info';
    }

    function getErrorMessage(error, fallback) {
        const messages = {
            401: 'Your session has expired. Please sign in again.',
            403: 'You do not have permission to view inventory.',
            404: 'The requested inventory resource was not found.',
            409: 'The inventory request conflicts with the current system state.',
            422: 'The inventory request could not be processed with the submitted values.',
            500: 'The server could not complete the inventory request.'
        };

        return messages[error.status] || error.message || fallback;
    }

    function handleAuthError(error) {
        if (error.status === 401) {
            window.location.href = '../frontend/login.php';
        }
    }

    function showFeedback(message, type = 'success') {
        elements.feedback.className = `alert alert-${type}`;
        elements.feedback.textContent = message;
    }

    function hideFeedback() {
        elements.feedback.className = 'alert d-none';
        elements.feedback.textContent = '';
    }

    function normalizeRisk(risk) {
        return ['Critical', 'High', 'Medium', 'Low', 'Unknown'].includes(risk) ? risk : 'Unknown';
    }

    function clinicNameFor(clinicId) {
        const clinic = state.clinics.find(item => String(item.clinic_id) === String(clinicId));

        if (clinic && clinic.clinic_name) {
            return clinic.clinic_name;
        }

        if (clinicId === null || clinicId === undefined || clinicId === '') {
            return 'Not provided';
        }

        return `Clinic ${clinicId}`;
    }

    function riskLookup() {
        const lookup = new Map();

        state.riskData.forEach(item => {
            if (item && item.inventory_id !== undefined) {
                lookup.set(String(item.inventory_id), item);
            }
        });

        return lookup;
    }

    function getInventoryRows() {
        const lookup = riskLookup();
        const query = elements.search.value.trim().toLowerCase();
        const riskValue = elements.riskFilter.value;

        return state.inventory.filter(item => {
            const inventoryRisk = normalizeRisk(lookup.get(String(item.inventory_id))?.stock_out_risk || 'Unknown');
            const clinicLabel = clinicNameFor(item.clinic_id);
            const matchesQuery = !query || [
                item.item_name,
                item.category,
                clinicLabel,
                item.clinic_id,
                item.item_id
            ].some(value => String(value ?? '').toLowerCase().includes(query));

            const matchesRisk = !riskValue || inventoryRisk === riskValue;

            return matchesQuery && matchesRisk;
        });
    }

    function createActionButton(label, icon, action, item) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary users-action';
        button.dataset.action = action;
        button.dataset.inventoryId = item.inventory_id;
        button.dataset.itemId = item.item_id;
        button.title = label;
        button.setAttribute('aria-label', `${label}: ${item.item_name || 'Inventory item'}`);
        button.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span class="visually-hidden">${label}</span>`;
        return button;
    }

    function createRiskBadge(risk) {
        const badge = document.createElement('span');
        const normalizedRisk = normalizeRisk(risk);
        badge.className = `badge ${riskBadgeClass(normalizedRisk)}`;
        badge.textContent = normalizedRisk;
        return badge;
    }

    function renderInventory() {
        const filtered = getInventoryRows();
        const lookup = riskLookup();

        elements.tableBody.replaceChildren();
        elements.empty.classList.toggle('d-none', filtered.length > 0);
        elements.count.textContent = `${filtered.length} of ${state.inventory.length} items`;

        if (filtered.length === 0) {
            elements.tableBody.classList.add('d-none');
            return;
        }

        elements.tableBody.classList.remove('d-none');

        filtered.forEach(item => {
            const row = document.createElement('tr');
            const itemCell = document.createElement('td');
            const itemName = document.createElement('strong');
            itemName.textContent = displayValue(item.item_name);
            const itemMeta = document.createElement('span');
            itemMeta.className = 'users-email';
            itemMeta.textContent = displayValue(item.category);
            itemCell.append(itemName, itemMeta);

            const clinicCell = document.createElement('td');
            clinicCell.textContent = clinicNameFor(item.clinic_id);

            const quantityCell = document.createElement('td');
            quantityCell.textContent = formatNumber(item.quantity_available);

            const usageCell = document.createElement('td');
            usageCell.textContent = formatNumber(item.average_daily_usage);

            const daysCell = document.createElement('td');
            const daysValue = item.days_remaining !== null && item.days_remaining !== undefined ? Number(item.days_remaining) : null;
            daysCell.textContent = Number.isFinite(daysValue) ? `${formatNumber(daysValue)} days` : 'Unknown';

            const riskCell = document.createElement('td');
            const riskItem = lookup.get(String(item.inventory_id));
            const riskValue = normalizeRisk(riskItem?.stock_out_risk || 'Unknown');
            riskCell.append(createRiskBadge(riskValue));

            const actionsCell = document.createElement('td');
            actionsCell.className = 'text-end text-nowrap';
            if (role === 'Clinic Staff') {
                actionsCell.append(createActionButton('Record usage', 'bi-clipboard-minus', 'record-usage', item));
            }
            actionsCell.append(createActionButton('View details', 'bi-eye', 'details', item));

            row.append(itemCell, clinicCell, quantityCell, usageCell, daysCell, riskCell, actionsCell);
            elements.tableBody.append(row);
        });
    }

    function updateSummary() {
        const scope = state.selectedClinicId
            ? state.riskData.filter(item => String(item.clinic_id) === String(state.selectedClinicId))
            : state.riskData;

        const counts = {
            Critical: 0,
            High: 0,
            Medium: 0,
            Low: 0,
            Unknown: 0
        };

        scope.forEach(item => {
            const risk = normalizeRisk(item.stock_out_risk);
            if (Object.prototype.hasOwnProperty.call(counts, risk)) {
                counts[risk] += 1;
            }
        });

        elements.statTotal.textContent = state.inventory.length;
        elements.statCritical.textContent = counts.Critical;
        elements.statHigh.textContent = counts.High;
        elements.statMedium.textContent = counts.Medium;
        elements.statLow.textContent = counts.Low;
    }

    function setTableLoading(label = 'Loading inventory...') {
        elements.tableBody.classList.remove('d-none');
        elements.empty.classList.add('d-none');
        elements.tableBody.innerHTML = `<tr><td colspan="7"><span class="loading-indicator"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>${label}</span></td></tr>`;
    }

    function renderClinicOptions() {
        if (role !== 'Admin' || !elements.clinicFilter) {
            return;
        }

        elements.clinicFilter.replaceChildren();

        if (!state.clinics.length) {
            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = 'No clinics available';
            elements.clinicFilter.append(emptyOption);
            elements.clinicFilter.disabled = true;
            state.selectedClinicId = '';
            return;
        }

        state.clinics.forEach(clinic => {
            const option = document.createElement('option');
            option.value = clinic.clinic_id ?? '';
            option.textContent = clinic.clinic_name || `Clinic ${clinic.clinic_id}`;
            elements.clinicFilter.append(option);
        });

        if (!state.selectedClinicId || !state.clinics.some(clinic => String(clinic.clinic_id) === String(state.selectedClinicId))) {
            state.selectedClinicId = String(state.clinics[0].clinic_id);
        }

        elements.clinicFilter.disabled = false;
        elements.clinicFilter.value = state.selectedClinicId;
    }

    function renderStockClinicOptions() {
        if (!elements.stockClinicId) {
            return;
        }

        elements.stockClinicId.replaceChildren();

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = state.clinics.length ? 'Select clinic...' : 'No clinics available';
        elements.stockClinicId.append(defaultOption);

        state.clinics.forEach(clinic => {
            const option = document.createElement('option');
            option.value = clinic.clinic_id;
            option.textContent = clinic.clinic_name || `Clinic ${clinic.clinic_id}`;
            elements.stockClinicId.append(option);
        });

        if (state.selectedClinicId) {
            elements.stockClinicId.value = state.selectedClinicId;
        }
    }

    async function loadActiveItems() {
        if (role !== 'Admin') {
            return;
        }

        try {
            const response = await MedLineAPI.requestJson('items/list.php');
            state.items = Array.isArray(response.data) ? response.data : [];
            renderStockItemOptions();
        } catch (error) {
            console.error('Unable to load items for stock modal', error);
        }
    }

    function renderStockItemOptions() {
        if (!elements.stockItemId) {
            return;
        }

        elements.stockItemId.replaceChildren();

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = state.items.length ? 'Select item...' : 'No items available';
        elements.stockItemId.append(defaultOption);

        state.items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.item_id;
            const categoryText = item.category ? ` (${item.category})` : '';
            option.textContent = `${item.item_name}${categoryText}`;
            elements.stockItemId.append(option);
        });
    }

    async function loadClinics() {
        if (role !== 'Admin') {
            return;
        }

        try {
            const response = await MedLineAPI.requestJson('clinics/list.php');
            state.clinics = Array.isArray(response.data) ? response.data : [];
            renderClinicOptions();
            renderStockClinicOptions();
            await Promise.all([
                loadInventory(),
                loadActiveItems()
            ]);
        } catch (error) {
            showFeedback(getErrorMessage(error, 'Unable to load clinics.'), 'danger');
            handleAuthError(error);
        }
    }

    async function loadInventory() {
        setTableLoading();

        try {
            const query = new URLSearchParams();
            if (role === 'Admin') {
                if (!state.selectedClinicId) {
                    const fallbackClinic = state.clinics[0];
                    state.selectedClinicId = fallbackClinic ? String(fallbackClinic.clinic_id) : '';
                }

                if (state.selectedClinicId) {
                    query.set('clinic_id', state.selectedClinicId);
                }
            }

            const url = query.toString() ? `inventory/get.php?${query.toString()}` : 'inventory/get.php';
            const response = await MedLineAPI.requestJson(url);
            state.inventory = Array.isArray(response.data) ? response.data : [];
            renderInventory();
            updateSummary();
        } catch (error) {
            elements.count.textContent = 'Unable to load inventory';
            elements.tableBody.innerHTML = `<tr><td colspan="7"><div class="error-state"><i class="bi bi-exclamation-circle error-state-icon" aria-hidden="true"></i><p class="mb-3">${getErrorMessage(error, 'Unable to load inventory. Please try again.')}</p><button class="btn btn-primary btn-sm" type="button" id="retry-inventory"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Retry</button></div></td></tr>`;
            const retryButton = elements.tableBody.querySelector('#retry-inventory');
            if (retryButton) {
                retryButton.addEventListener('click', () => {
                    hideFeedback();
                    loadInventory();
                });
            }
            handleAuthError(error);
        }
    }

    async function loadRiskData() {
        try {
            const response = await MedLineAPI.requestJson('inventory/stock_risk.php');
            state.riskData = Array.isArray(response.data) ? response.data : [];
            updateSummary();
            renderInventory();
        } catch (error) {
            showFeedback(getErrorMessage(error, 'Unable to load stock-out risk data.'), 'danger');
            handleAuthError(error);
        }
    }

    async function refreshInventory() {
        hideFeedback();
        showFeedback('Refreshing inventory and stock-risk data…', 'info');

        if (role === 'Admin') {
            await loadClinics();
        }

        await Promise.all([
            loadInventory(),
            loadRiskData()
        ]);

        showFeedback('Inventory and stock-risk data refreshed.', 'success');
    }

    function detailRow(label, value) {
        const row = document.createElement('div');
        row.className = 'user-detail-row';
        const key = document.createElement('span');
        key.textContent = label;
        const content = document.createElement('strong');
        content.textContent = displayValue(value);
        row.append(key, content);
        return row;
    }

    async function showDetails(inventoryId) {
        const inventoryItem = state.inventory.find(item => String(item.inventory_id) === String(inventoryId));
        const lookup = riskLookup();

        if (!inventoryItem) {
            elements.detailsContent.innerHTML = '<div class="alert alert-warning mb-0">Inventory item could not be found.</div>';
            detailsModal.show();
            return;
        }

        const riskItem = lookup.get(String(inventoryId));
        const riskValue = normalizeRisk(riskItem?.stock_out_risk || 'Unknown');

        elements.detailsContent.innerHTML = '';

        [
            ['Item', inventoryItem.item_name],
            ['Clinic', clinicNameFor(inventoryItem.clinic_id)],
            ['Category', inventoryItem.category],
            ['Available Quantity', formatNumber(inventoryItem.quantity_available)],
            ['Daily Usage', formatNumber(inventoryItem.average_daily_usage)],
            ['Days Remaining', Number.isFinite(Number(inventoryItem.days_remaining)) ? `${formatNumber(inventoryItem.days_remaining)} days` : 'Unknown'],
            ['Stock-Out Risk', riskValue],
            ['Criticality Score', inventoryItem.criticality_score],
            ['Cold Chain Required', Number(inventoryItem.cold_chain_required) === 1 ? 'Yes' : 'No'],
            ['Last Updated', inventoryItem.last_updated]
        ].forEach(([label, value]) => {
            const wrapper = document.createElement('div');
            wrapper.append(detailRow(label, value));
            elements.detailsContent.append(wrapper);
        });

        detailsModal.show();
    }

    function clearAddItemForm() {
        if (!elements.addItemForm) return;
        elements.addItemForm.reset();
        if (elements.addItemError) {
            elements.addItemError.classList.add('d-none');
            elements.addItemError.textContent = '';
        }
        if (elements.itemCriticality) {
            elements.itemCriticality.value = '50';
        }
        if (elements.itemColdChain) {
            elements.itemColdChain.value = '0';
        }
        toggleColdChainFields();
    }

    function toggleColdChainFields() {
        if (!elements.itemColdChain || !elements.itemTempFields) return;
        const isColdChain = elements.itemColdChain.value === '1';
        elements.itemTempFields.classList.toggle('d-none', !isColdChain);
        if (elements.itemMinTemp) {
            elements.itemMinTemp.disabled = !isColdChain;
            elements.itemMinTemp.required = isColdChain;
            if (!isColdChain) elements.itemMinTemp.value = '';
        }
        if (elements.itemMaxTemp) {
            elements.itemMaxTemp.disabled = !isColdChain;
            elements.itemMaxTemp.required = isColdChain;
            if (!isColdChain) elements.itemMaxTemp.value = '';
        }
    }

    function setButtonBusy(button, isBusy, busyText) {
        if (!button) return;
        if (isBusy) {
            if (!button.dataset.defaultHtml) {
                button.dataset.defaultHtml = button.innerHTML;
            }
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${busyText}`;
        } else {
            button.disabled = false;
            if (button.dataset.defaultHtml) {
                button.innerHTML = button.dataset.defaultHtml;
            }
        }
    }

    async function handleAddItem(event) {
        event.preventDefault();
        if (!elements.addItemForm) return;

        if (elements.addItemError) {
            elements.addItemError.classList.add('d-none');
            elements.addItemError.textContent = '';
        }

        const name = elements.itemName ? elements.itemName.value.trim() : '';
        const category = elements.itemCategory ? elements.itemCategory.value.trim() : '';
        const rawCriticality = elements.itemCriticality ? elements.itemCriticality.value.trim() : '';
        const isColdChain = elements.itemColdChain ? elements.itemColdChain.value === '1' : false;
        const minTempRaw = elements.itemMinTemp ? elements.itemMinTemp.value.trim() : '';
        const maxTempRaw = elements.itemMaxTemp ? elements.itemMaxTemp.value.trim() : '';

        if (!name) {
            if (elements.addItemError) {
                elements.addItemError.textContent = 'Please enter an item name.';
                elements.addItemError.classList.remove('d-none');
            }
            if (elements.itemName) elements.itemName.focus();
            return;
        }

        const criticality = rawCriticality !== '' ? Number(rawCriticality) : 50;
        if (Number.isNaN(criticality) || criticality < 0 || criticality > 100) {
            if (elements.addItemError) {
                elements.addItemError.textContent = 'Criticality score must be between 0 and 100.';
                elements.addItemError.classList.remove('d-none');
            }
            if (elements.itemCriticality) elements.itemCriticality.focus();
            return;
        }

        let minTemp = null;
        let maxTemp = null;

        if (isColdChain) {
            if (minTempRaw === '' || maxTempRaw === '') {
                if (elements.addItemError) {
                    elements.addItemError.textContent = 'Minimum and maximum temperatures are required when cold chain is required.';
                    elements.addItemError.classList.remove('d-none');
                }
                return;
            }

            minTemp = Number(minTempRaw);
            maxTemp = Number(maxTempRaw);

            if (Number.isNaN(minTemp) || Number.isNaN(maxTemp)) {
                if (elements.addItemError) {
                    elements.addItemError.textContent = 'Temperature values must be valid numbers.';
                    elements.addItemError.classList.remove('d-none');
                }
                return;
            }

            if (minTemp >= maxTemp) {
                if (elements.addItemError) {
                    elements.addItemError.textContent = 'Minimum temperature must be strictly less than maximum temperature.';
                    elements.addItemError.classList.remove('d-none');
                }
                if (elements.itemMaxTemp) elements.itemMaxTemp.focus();
                return;
            }
        }

        const payload = {
            item_name: name,
            category: category || null,
            criticality_score: criticality,
            cold_chain_required: isColdChain,
            minimum_temperature: minTemp,
            maximum_temperature: maxTemp
        };

        setButtonBusy(elements.saveItemButton, true, 'Saving Item...');

        try {
            const response = await MedLineAPI.requestJson('items/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (addItemModal) {
                addItemModal.hide();
            }
            clearAddItemForm();
            showFeedback(response.message || `Item "${name}" created successfully.`, 'success');
            await Promise.all([
                loadInventory(),
                loadRiskData(),
                loadActiveItems()
            ]);
        } catch (error) {
            if (elements.addItemError) {
                elements.addItemError.textContent = getErrorMessage(error, 'Unable to create medical item. Please check the values.');
                elements.addItemError.classList.remove('d-none');
            }
            handleAuthError(error);
        } finally {
            setButtonBusy(elements.saveItemButton, false);
        }
    }

    function clearAddStockForm() {
        if (!elements.addStockForm) return;
        elements.addStockForm.reset();
        if (elements.addStockError) {
            elements.addStockError.classList.add('d-none');
            elements.addStockError.textContent = '';
        }
        if (elements.stockClinicId && state.selectedClinicId) {
            elements.stockClinicId.value = state.selectedClinicId;
        }
    }

    async function handleAddStock(event) {
        event.preventDefault();
        if (!elements.addStockForm) return;

        if (elements.addStockError) {
            elements.addStockError.classList.add('d-none');
            elements.addStockError.textContent = '';
        }

        const clinicId = elements.stockClinicId ? Number(elements.stockClinicId.value) : 0;
        const itemId = elements.stockItemId ? Number(elements.stockItemId.value) : 0;
        const rawQuantity = elements.stockQuantity ? elements.stockQuantity.value.trim() : '';

        if (!clinicId) {
            if (elements.addStockError) {
                elements.addStockError.textContent = 'Please select a destination clinic.';
                elements.addStockError.classList.remove('d-none');
            }
            if (elements.stockClinicId) elements.stockClinicId.focus();
            return;
        }

        if (!itemId) {
            if (elements.addStockError) {
                elements.addStockError.textContent = 'Please select a medical item.';
                elements.addStockError.classList.remove('d-none');
            }
            if (elements.stockItemId) elements.stockItemId.focus();
            return;
        }

        const quantity = Number(rawQuantity);
        if (rawQuantity === '' || Number.isNaN(quantity) || quantity <= 0) {
            if (elements.addStockError) {
                elements.addStockError.textContent = 'Please enter a valid quantity greater than zero.';
                elements.addStockError.classList.remove('d-none');
            }
            if (elements.stockQuantity) elements.stockQuantity.focus();
            return;
        }

        const payload = {
            clinic_id: clinicId,
            item_id: itemId,
            quantity_to_add: quantity
        };

        setButtonBusy(elements.saveStockButton, true, 'Adding Stock...');

        try {
            const response = await MedLineAPI.requestJson('inventory/add_stock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (addStockModal) {
                addStockModal.hide();
            }
            clearAddStockForm();

            const clinicName = clinicNameFor(clinicId);
            const itemName = response.data?.item_name || 'Item';
            const newQty = response.data?.quantity_available !== undefined ? formatNumber(response.data.quantity_available) : '';
            const successMsg = response.message || `Added ${formatNumber(quantity)} units of ${itemName} to ${clinicName}. New total: ${newQty}`;

            showFeedback(successMsg, 'success');

            if (state.selectedClinicId && String(state.selectedClinicId) !== String(clinicId)) {
                state.selectedClinicId = String(clinicId);
                if (elements.clinicFilter) {
                    elements.clinicFilter.value = state.selectedClinicId;
                }
            }

            await Promise.all([
                loadInventory(),
                loadRiskData()
            ]);
        } catch (error) {
            if (elements.addStockError) {
                elements.addStockError.textContent = getErrorMessage(error, 'Unable to add stock to inventory. Please check the values.');
                elements.addStockError.classList.remove('d-none');
            }
            handleAuthError(error);
        } finally {
            setButtonBusy(elements.saveStockButton, false);
        }
    }

    function renderUsageItemOptions(preselectedItemId = null) {
        if (!elements.usageItemId) {
            return;
        }

        elements.usageItemId.replaceChildren();

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = state.inventory.length ? 'Select item...' : 'No inventory items available';
        elements.usageItemId.append(defaultOption);

        state.inventory.forEach(item => {
            const option = document.createElement('option');
            option.value = item.item_id;
            const categoryText = item.category ? ` (${item.category})` : '';
            option.textContent = `${item.item_name}${categoryText}`;
            elements.usageItemId.append(option);
        });

        if (preselectedItemId && state.inventory.some(item => String(item.item_id) === String(preselectedItemId))) {
            elements.usageItemId.value = String(preselectedItemId);
        }

        updateUsageAvailableStock();
    }

    function updateUsageAvailableStock() {
        if (!elements.usageAvailableQty) {
            return;
        }

        const itemId = elements.usageItemId ? elements.usageItemId.value : '';
        if (!itemId) {
            elements.usageAvailableQty.textContent = '--';
            return;
        }

        const invItem = state.inventory.find(item => String(item.item_id) === String(itemId));
        if (invItem) {
            elements.usageAvailableQty.textContent = `${formatNumber(invItem.quantity_available)} units`;
        } else {
            elements.usageAvailableQty.textContent = '--';
        }
    }

    function clearRecordUsageForm() {
        if (!elements.recordUsageForm) {
            return;
        }

        elements.recordUsageForm.reset();
        if (elements.recordUsageError) {
            elements.recordUsageError.classList.add('d-none');
            elements.recordUsageError.textContent = '';
        }
        updateUsageAvailableStock();
    }

    function openRecordUsageModal(preselectedItemId = null) {
        renderUsageItemOptions(preselectedItemId);
        if (recordUsageModal) {
            recordUsageModal.show();
        }
    }

    async function handleRecordUsage(event) {
        event.preventDefault();
        if (!elements.recordUsageForm) {
            return;
        }

        if (elements.recordUsageError) {
            elements.recordUsageError.classList.add('d-none');
            elements.recordUsageError.textContent = '';
        }

        const itemId = elements.usageItemId ? Number(elements.usageItemId.value) : 0;
        const rawQuantity = elements.usageQuantity ? elements.usageQuantity.value.trim() : '';

        if (!itemId) {
            if (elements.recordUsageError) {
                elements.recordUsageError.textContent = 'Please select a medical item from your inventory.';
                elements.recordUsageError.classList.remove('d-none');
            }
            if (elements.usageItemId) elements.usageItemId.focus();
            return;
        }

        const quantity = Number(rawQuantity);
        if (rawQuantity === '' || Number.isNaN(quantity) || quantity <= 0) {
            if (elements.recordUsageError) {
                elements.recordUsageError.textContent = 'Please enter a valid quantity greater than zero.';
                elements.recordUsageError.classList.remove('d-none');
            }
            if (elements.usageQuantity) elements.usageQuantity.focus();
            return;
        }

        const invItem = state.inventory.find(item => String(item.item_id) === String(itemId));
        if (invItem && quantity > Number(invItem.quantity_available)) {
            if (elements.recordUsageError) {
                elements.recordUsageError.textContent = `Quantity used (${formatNumber(quantity)}) exceeds current stock available (${formatNumber(invItem.quantity_available)}).`;
                elements.recordUsageError.classList.remove('d-none');
            }
            if (elements.usageQuantity) elements.usageQuantity.focus();
            return;
        }

        const payload = {
            item_id: itemId,
            quantity_used: quantity
        };

        setButtonBusy(elements.saveUsageButton, true, 'Recording Usage...');

        try {
            const response = await MedLineAPI.requestJson('inventory/record_usage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (recordUsageModal) {
                recordUsageModal.hide();
            }
            clearRecordUsageForm();

            const itemName = response.data?.item_name || invItem?.item_name || 'Item';
            const newQty = response.data?.quantity_available !== undefined ? formatNumber(response.data.quantity_available) : '';
            const successMsg = response.message || `Recorded usage of ${formatNumber(quantity)} units of ${itemName}. Remaining stock: ${newQty}`;

            showFeedback(successMsg, 'success');

            await Promise.all([
                loadInventory(),
                loadRiskData()
            ]);
        } catch (error) {
            if (elements.recordUsageError) {
                elements.recordUsageError.textContent = getErrorMessage(error, 'Unable to record inventory usage. Please check the values.');
                elements.recordUsageError.classList.remove('d-none');
            }
            handleAuthError(error);
        } finally {
            setButtonBusy(elements.saveUsageButton, false);
        }
    }

    if (role === 'Admin') {
        if (elements.addItemForm) {
            elements.addItemForm.addEventListener('submit', handleAddItem);
        }
        if (elements.itemColdChain) {
            elements.itemColdChain.addEventListener('change', toggleColdChainFields);
        }
        if (elements.addItemModal) {
            elements.addItemModal.addEventListener('hidden.bs.modal', clearAddItemForm);
        }
        if (elements.addStockForm) {
            elements.addStockForm.addEventListener('submit', handleAddStock);
        }
        if (elements.addStockModal) {
            elements.addStockModal.addEventListener('show.bs.modal', () => {
                renderStockClinicOptions();
                renderStockItemOptions();
                if (!state.items.length) {
                    loadActiveItems();
                }
            });
            elements.addStockModal.addEventListener('hidden.bs.modal', clearAddStockForm);
        }
    } else if (role === 'Clinic Staff') {
        if (elements.recordUsageForm) {
            elements.recordUsageForm.addEventListener('submit', handleRecordUsage);
        }
        if (elements.usageItemId) {
            elements.usageItemId.addEventListener('change', updateUsageAvailableStock);
        }
        if (elements.recordUsageModal) {
            elements.recordUsageModal.addEventListener('show.bs.modal', () => {
                if (!elements.usageItemId.value) {
                    renderUsageItemOptions();
                }
            });
            elements.recordUsageModal.addEventListener('hidden.bs.modal', clearRecordUsageForm);
        }
    }

    elements.search.addEventListener('input', renderInventory);
    elements.riskFilter.addEventListener('change', renderInventory);
    elements.refreshButton.addEventListener('click', refreshInventory);

    if (role === 'Admin' && elements.clinicFilter) {
        elements.clinicFilter.addEventListener('change', event => {
            state.selectedClinicId = event.target.value;
            loadInventory();
            updateSummary();
        });
    }

    elements.tableBody.addEventListener('click', event => {
        const recordUsageBtn = event.target.closest('[data-action="record-usage"]');
        if (recordUsageBtn) {
            const itemId = recordUsageBtn.dataset.itemId;
            openRecordUsageModal(itemId);
            return;
        }

        const actionButton = event.target.closest('[data-action="details"]');
        if (!actionButton) {
            return;
        }

        const inventoryId = actionButton.dataset.inventoryId;
        if (inventoryId) {
            showDetails(inventoryId);
        }
    });

    if (role === 'Admin') {
        loadClinics();
    } else {
        loadInventory();
    }

    loadRiskData();
});
