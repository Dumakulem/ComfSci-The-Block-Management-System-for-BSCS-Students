// Stores a student's configurable grade breakdown only in the current browser.
(() => {
    const storageKey = 'comfSciGradeBreakdown.v1';
    const itemsElement = document.getElementById('gradeItems');
    const addButton = document.getElementById('addGradeItem');
    const clearButton = document.getElementById('clearGradeItems');
    const weightSummary = document.getElementById('weightSummary');
    const currentAverage = document.getElementById('currentAverage');
    const targetGrade = document.getElementById('targetGrade');
    const neededAverage = document.getElementById('neededAverage');
    const gradeMessage = document.getElementById('gradeMessage');

    const createItem = (name = '', score = '', possible = '', weight = '') => ({
        id: crypto.randomUUID(),
        name,
        score,
        possible,
        weight
    });

    const readItems = () => {
        try {
            const parsedItems = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return Array.isArray(parsedItems)
                ? parsedItems.filter(item => item && typeof item === 'object')
                : [];
        } catch {
            return [];
        }
    };

    let items = readItems();

    const saveItems = () => {
        localStorage.setItem(storageKey, JSON.stringify(items));
    };

    const addField = (row, label, type, value, field, min, max) => {
        const cell = document.createElement('td');
        const input = document.createElement('input');
        input.type = type;
        input.value = value;
        input.dataset.field = field;
        input.setAttribute('aria-label', label);
        if (min !== null) input.min = String(min);
        if (max !== null) input.max = String(max);
        if (type === 'number') input.step = '0.01';
        cell.append(input);
        row.append(cell);
    };

    const renderItems = () => {
        itemsElement.replaceChildren();

        items.forEach(item => {
            const row = document.createElement('tr');
            row.dataset.id = item.id;
            addField(row, 'Category name', 'text', item.name, 'name', null, null);
            addField(row, 'Score earned', 'number', item.score, 'score', 0, null);
            addField(row, 'Score possible', 'number', item.possible, 'possible', 0.01, null);
            addField(row, 'Category weight in percent', 'number', item.weight, 'weight', 0, 100);

            const removeCell = document.createElement('td');
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.dataset.remove = item.id;
            removeButton.textContent = 'Remove';
            removeButton.setAttribute('aria-label', 'Remove ' + (item.name || 'category'));
            removeCell.append(removeButton);
            row.append(removeCell);
            itemsElement.append(row);
        });

        calculate();
    };

    const calculate = () => {
        let totalWeight = 0;
        let weightedPoints = 0;
        let invalid = false;

        items.forEach(item => {
            const score = Number(item.score);
            const possible = Number(item.possible);
            const weight = Number(item.weight);
            if (item.score === '' || item.possible === '' || item.weight === '') return;
            if (!Number.isFinite(score) || !Number.isFinite(possible) || !Number.isFinite(weight)
                || score < 0 || possible <= 0 || score > possible || weight < 0 || weight > 100) {
                invalid = true;
                return;
            }

            totalWeight += weight;
            weightedPoints += (score / possible * 100) * weight;
        });

        weightSummary.textContent = `Configured weight: ${totalWeight.toFixed(2)}%`;
        currentAverage.textContent = totalWeight > 0 && !invalid
            ? `${(weightedPoints / totalWeight).toFixed(2)}%`
            : '—';

        const target = Number(targetGrade.value);
        const remainingWeight = 100 - totalWeight;
        if (!invalid && remainingWeight > 0 && target >= 0 && target <= 100) {
            const required = (target * 100 - weightedPoints) / remainingWeight;
            neededAverage.textContent = `${required.toFixed(2)}%`;
        } else {
            neededAverage.textContent = '—';
        }

        if (invalid) {
            gradeMessage.textContent = 'Check scores, possible points, and weights. Scores cannot exceed the possible amount.';
        } else if (totalWeight > 100) {
            gradeMessage.textContent = 'Category weights exceed 100%; adjust them before calculating a target.';
        } else if (totalWeight === 100) {
            gradeMessage.textContent = 'All category weights are assigned; no weight remains for a target calculation.';
        } else if (remainingWeight > 0 && Number(neededAverage.textContent.replace('%', '')) > 100) {
            gradeMessage.textContent = 'The target would require an average above 100% on the remaining weight.';
        } else {
            gradeMessage.textContent = `${remainingWeight.toFixed(2)}% of the final grade is unassigned.`;
        }
    };

    addButton.addEventListener('click', () => {
        items.push(createItem());
        saveItems();
        renderItems();
    });

    clearButton.addEventListener('click', () => {
        items = [];
        saveItems();
        renderItems();
    });

    itemsElement.addEventListener('input', event => {
        const row = event.target.closest('tr');
        if (!row || !event.target.dataset.field) return;
        const item = items.find(value => value.id === row.dataset.id);
        if (!item) return;
        item[event.target.dataset.field] = event.target.value;
        saveItems();
        calculate();
    });

    itemsElement.addEventListener('click', event => {
        const itemId = event.target.dataset.remove;
        if (!itemId) return;
        items = items.filter(item => item.id !== itemId);
        saveItems();
        renderItems();
    });

    targetGrade.addEventListener('input', calculate);
    renderItems();
})();
