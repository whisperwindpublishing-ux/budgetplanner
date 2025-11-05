# Budget Planner Pro - Frontend Shortcode Usage

## Purchase Reporting Form Shortcode

The `[budget_purchase_form]` shortcode allows users to report purchases on the frontend of your WordPress site.

### Usage

Simply add the following shortcode to any page or post:

```
[budget_purchase_form]
```

### Features

1. **Year Selection**: Users first select a budget year from a dropdown populated with available years from the database.

2. **Account Selection**: After selecting a year, the form dynamically loads all accounts for that year via AJAX. This ensures quick loading and doesn't overwhelm the browser with data.

3. **Line Item Selection**: Once an account is selected, the form loads only the line items for that specific account. Items that have already been purchased show their purchase date.

4. **Purchase Information**: Users can then:
   - Select a purchase date
   - Add optional comments
   - Submit the purchase report

### Performance Benefits

- **AJAX-Based Loading**: Data is loaded only when needed, not all at once
- **Cascading Filters**: Each selection narrows down the options for the next field
- **Minimal Server Load**: Only relevant data is queried based on user selections
- **No Page Refreshes**: All interactions happen asynchronously for a smooth user experience

### Security

- All AJAX requests are protected with WordPress nonces
- Input validation is performed on both client and server side
- Data is properly sanitized before database updates

### Example Use Cases

1. **Budget Committee Members**: Can report when items are purchased without needing admin access
2. **Department Heads**: Can update purchase status for their budget items
3. **Finance Teams**: Can track purchase dates across multiple years and accounts

### Permissions

The shortcode is available to all users (logged in and logged out). If you need to restrict access, you can use a membership plugin or add custom capability checks to the AJAX handlers.
