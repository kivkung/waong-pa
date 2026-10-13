// ใช้กับฟอร์มสร้างรอบ รีเซ็ตรอบ และ My Schedule
function updateRecurrenceCount(frequencyInput, countInput) {
    if (frequencyInput.value === 'none') {
        countInput.required = false;
        countInput.disabled = true;
        countInput.value = 1;
    } else {
        countInput.disabled = false;
        countInput.required = true;
        if (countInput.value === '' || Number(countInput.value) < 2) {
            countInput.value = 2;
        }
    }
}

const frequencyInputs = document.querySelectorAll('[data-recurrence-count]');
for (const frequencyInput of frequencyInputs) {
    const countId = frequencyInput.getAttribute('data-recurrence-count');
    const countInput = document.getElementById(countId);
    if (countInput === null) {
        continue;
    }

    updateRecurrenceCount(frequencyInput, countInput);
    frequencyInput.addEventListener('change', function () {
        updateRecurrenceCount(frequencyInput, countInput);
    });
}
