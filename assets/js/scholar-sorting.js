/**
 * Google Scholar Profile - Table Sorting
 * Handles interactive sorting for publications table
 */

(function() {
  'use strict';

  let currentSort = null;
  let currentOrder = 'desc';

  /**
   * Initialize sorting functionality when DOM is ready
   */
  function initSorting() {
      const tables = document.querySelectorAll('.scholar-publications-table[data-sortable="true"]');
      
      tables.forEach(table => {
          const headers = table.querySelectorAll('th.sortable');
          headers.forEach(header => {
              header.addEventListener('click', handleSort);
              header.addEventListener('keydown', handleKeyDown);
          });
      });
  }

  /**
   * Handle keyboard navigation for accessibility
   */
  function handleKeyDown(event) {
      if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          handleSort.call(this, event);
      }
  }

  /**
   * Handle sort button clicks
   */
  function handleSort(event) {
      const header = event.currentTarget;
      const table = header.closest('table');
      const sortBy = header.getAttribute('data-sort');
      
      // Determine sort order
      if (currentSort === sortBy) {
          currentOrder = currentOrder === 'desc' ? 'asc' : 'desc';
      } else {
          currentOrder = 'desc'; // Default to descending for new sorts
          currentSort = sortBy;
      }

      // Update visual indicators
      updateSortIndicators(table, header, currentOrder);
      
      // Perform the sort
      sortTable(table, sortBy, currentOrder);
      
      // Update accessibility
      updateAriaLabel(header, sortBy, currentOrder);
  }

  /**
   * Update visual sort indicators
   */
  function updateSortIndicators(table, activeHeader, order) {
      // Clear all existing indicators
      const allHeaders = table.querySelectorAll('th.sortable');
      allHeaders.forEach(header => {
          header.classList.remove('sorted-asc', 'sorted-desc');
          const arrow = header.querySelector('.sort-arrow');
          if (arrow) {
              arrow.textContent = '';
          }
      });

      // Set indicator for active header
      activeHeader.classList.add(order === 'asc' ? 'sorted-asc' : 'sorted-desc');
      const arrow = activeHeader.querySelector('.sort-arrow');
      if (arrow) {
          arrow.textContent = order === 'asc' ? ' ↑' : ' ↓';
      }
  }

  /**
   * Update ARIA label for accessibility
   */
  function updateAriaLabel(header, sortBy, currentOrder) {
      const nextOrder = currentOrder === 'desc' ? 'ascending' : 'descending';
      const sortLabel = getSortLabel(sortBy);
      header.setAttribute('aria-label', `Sort by ${sortLabel} (${nextOrder})`);
  }

  /**
   * Get human-readable sort label
   */
  function getSortLabel(sortBy) {
      const labels = {
          'title': 'title',
          'year': 'year',
          'citations': 'citations'
      };
      return labels[sortBy] || sortBy;
  }

  /**
   * Sort the table rows
   */
  function sortTable(table, sortBy, order) {
      const tbody = table.querySelector('.publications-tbody');
      if (!tbody) return;

      const rows = Array.from(tbody.querySelectorAll('tr'));
      
      rows.sort((a, b) => {
          let valueA, valueB;

          switch (sortBy) {
              case 'title':
                  valueA = a.querySelector('.scholar-publication-title')?.textContent?.trim().toLowerCase() || '';
                  valueB = b.querySelector('.scholar-publication-title')?.textContent?.trim().toLowerCase() || '';
                  break;
                  
              case 'year':
                  valueA = parseInt(a.querySelector('.publication-year')?.getAttribute('data-year') || '0');
                  valueB = parseInt(b.querySelector('.publication-year')?.getAttribute('data-year') || '0');
                  break;
                  
              case 'citations':
                  valueA = parseInt(a.querySelector('.publication-citations')?.getAttribute('data-citations') || '0');
                  valueB = parseInt(b.querySelector('.publication-citations')?.getAttribute('data-citations') || '0');
                  break;
                  
              default:
                  return 0;
          }

          if (valueA === valueB) {
              return 0;
          }

          if (order === 'asc') {
              return valueA < valueB ? -1 : 1;
          } else {
              return valueA > valueB ? -1 : 1;
          }
      });

      // Re-append sorted rows
      rows.forEach(row => tbody.appendChild(row));
      
      // Add visual feedback
      addSortFeedback(table);
  }

  /**
   * Add visual feedback after sorting
   */
  function addSortFeedback(table) {
      table.classList.add('sorting-updated');
      setTimeout(() => {
          table.classList.remove('sorting-updated');
      }, 300);
  }

  /**
   * Initialize when DOM is ready
   */
  if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initSorting);
  } else {
      initSorting();
  }

})();