<html>
<main>

</main>
<body>
</div>

<footer class="erp-footer">
    <span>ERP System</span>
</footer>

<script>
/*
 Ensure wide tables do not break layout.
 Wrap tables automatically if developer forgot.
*/
document.querySelectorAll('table').forEach(table => {
    if (!table.parentElement.classList.contains('table-wrapper')) {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-wrapper';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    }
});
</script>

</div> <!-- app-container -->
</body>
</html>

