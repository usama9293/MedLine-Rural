<?php

require_once __DIR__ . '/../config/auth.php';

requireRole('Driver', 'Dispatcher', 'Admin');

?>

<!DOCTYPE html>
<html>

<head>
    <title>Shipment Test</title>
</head>

<body>

<h2>Test Shipment Status</h2>

<p>
    Logged in as:
    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
</p>

<form id="shipment-form">

    <label>Shipment ID</label>

    <input
        type="number"
        name="shipment_id"
        value="1"
        required
    >

    <br><br>

    <label>Status</label>

    <select name="status">

        <option value="Dispatched">
            Dispatched
        </option>

        <option value="In Transit">
            In Transit
        </option>

        <option value="Delivered">
            Delivered
        </option>

    </select>

    <br><br>

    <button type="submit" id="submit-button">
        Update Shipment
    </button>

</form>

<pre id="response" aria-live="polite"></pre>

<script>
    const form = document.getElementById('shipment-form');
    const responseElement = document.getElementById('response');
    const submitButton = document.getElementById('submit-button');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submitButton.disabled = true;
        responseElement.textContent = 'Updating shipment...';

        const formData = new FormData(form);

        try {
            const response = await fetch('../api/shipments/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    shipment_id: Number(formData.get('shipment_id')),
                    status: formData.get('status')
                })
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Shipment update failed.');
            }

            responseElement.textContent = result.message;
        } catch (error) {
            responseElement.textContent = error.message;
        } finally {
            submitButton.disabled = false;
        }
    });
</script>

</body>

</html>