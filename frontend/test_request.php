<?php

require_once __DIR__ . '/../config/auth.php';

requireRole('Clinic Staff');

?>

<!DOCTYPE html>
<html>

<head>
   <title>Test Supply Request</title>
</head>

<body>

<h2>Test Supply Request</h2>

<p>
   Logged in as:
   <?php echo htmlspecialchars($_SESSION['full_name']); ?>
</p>

<form id="request-form">

   <label>Item ID:</label>
   <input
       type="number"
       name="item_id"
       value="1"
       required
   >

   <br><br>

   <label>Quantity:</label>
   <input
       type="number"
       name="quantity"
       value="20"
       min="1"
       required
   >

   <br><br>

   <label>Required By:</label>
   <input
       type="date"
       name="required_by"
       value="2026-08-25"
   >

   <br><br>

   <label>Notes:</label>
   <br>

   <textarea name="notes">Insulin stock is running low</textarea>

   <br><br>

   <button type="submit" id="submit-button">
       Create Supply Request
   </button>

</form>

<pre id="response" aria-live="polite"></pre>

<script>
   const form = document.getElementById('request-form');
   const responseElement = document.getElementById('response');
   const submitButton = document.getElementById('submit-button');

   form.addEventListener('submit', async (event) => {
       event.preventDefault();
       submitButton.disabled = true;
       responseElement.textContent = 'Creating supply request...';

       const formData = new FormData(form);

       try {
           const response = await fetch('../api/requests/create.php', {
               method: 'POST',
               headers: {
                   'Content-Type': 'application/json'
               },
               body: JSON.stringify({
                   item_id: Number(formData.get('item_id')),
                   quantity: Number(formData.get('quantity')),
                   required_by: formData.get('required_by') || '',
                   notes: formData.get('notes') || ''
               })
           });

           const result = await response.json();

           if (!response.ok || !result.success) {
               throw new Error(result.message || 'Supply request creation failed.');
           }

           responseElement.textContent = JSON.stringify(result, null, 2);
       } catch (error) {
           responseElement.textContent = error.message;
       } finally {
           submitButton.disabled = false;
       }
   });
</script>

</body>

</html>