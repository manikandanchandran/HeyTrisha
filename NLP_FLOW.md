# Complete NLP Flow Documentation

## Overview
This document describes the complete Natural Language Processing (NLP) flow for generating SQL queries from user input.

## Architecture: True NLP Implementation

The system uses **true NLP** where:
1. **Full user input** is sent to OpenAI (no filtering)
2. **Complete database schema** is sent to OpenAI (all tables, all columns)
3. **OpenAI uses NLP** to understand the query and generate SQL
4. **SQL is executed locally** on our database
5. **Results are displayed** in the frontend

## Complete Flow Diagram

```
┌─────────────┐
│   User      │
│  Input      │ "Can you give the overall sales data"
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────────┐
│  Frontend (chatbot.js)              │
│  - Captures user input              │
│  - Sends to Laravel API             │
└──────┬──────────────────────────────┘
       │ POST /api/query
       │ { query: "Can you give..." }
       ▼
┌─────────────────────────────────────┐
│  NLPController (Laravel)            │
│  Step 1: Detect fetch operation     │
│  Step 2: Get FULL database schema   │
└──────┬──────────────────────────────┘
       │
       ▼
┌─────────────────────────────────────┐
│  MySQLService                       │
│  - Fetches ALL tables               │
│  - Gets ALL columns for each table  │
│  - Returns complete schema          │
└──────┬──────────────────────────────┘
       │ Full Schema
       │ { wp_posts: [id, title, ...],
       │   wp_wc_order_stats: [...],
       │   ... all tables ... }
       ▼
┌─────────────────────────────────────┐
│  SQLGeneratorService                │
│  - Builds NLP prompt                │
│  - Sends to OpenAI API              │
└──────┬──────────────────────────────┘
       │ POST to OpenAI
       │ {
       │   user_query: "Can you...",
       │   full_schema: { all tables }
       │ }
       ▼
┌─────────────────────────────────────┐
│  OpenAI GPT-4                       │
│  - Uses NLP to understand query     │
│  - Analyzes full schema             │
│  - Generates SQL query              │
└──────┬──────────────────────────────┘
       │ SQL Query
       │ "SELECT SUM(total_sales) FROM wp_wc_order_stats..."
       ▼
┌─────────────────────────────────────┐
│  SQLGeneratorService                │
│  - Receives SQL from OpenAI         │
│  - Validates SQL format             │
│  - Returns SQL query                │
└──────┬──────────────────────────────┘
       │ SQL Query
       ▼
┌─────────────────────────────────────┐
│  MySQLService                       │
│  - Validates SQL (SELECT only)       │
│  - Validates table names exist       │
│  - Executes SQL locally             │
└──────┬──────────────────────────────┘
       │ Query Results
       │ [{ total_sales: 50000, ... }]
       ▼
┌─────────────────────────────────────┐
│  NLPController                      │
│  - Formats response                  │
│  - Returns to frontend               │
└──────┬──────────────────────────────┘
       │ JSON Response
       │ { success: true, data: [...] }
       ▼
┌─────────────────────────────────────┐
│  Frontend (chatbot.js)              │
│  - Displays results to user          │
└─────────────────────────────────────┘
```

## Step-by-Step Process

### Step 1: User Input
- User types natural language query in chatbot
- Example: "Can you give the overall sales data"

### Step 2: Frontend → Backend
- Frontend sends POST request to `/api/query`
- Payload: `{ query: "Can you give the overall sales data" }`

### Step 3: Detect Operation Type
- `NLPController::isFetchOperation()` detects it's a data retrieval query
- Routes to SQL generation flow

### Step 4: Get Full Database Schema
- `MySQLService::getCompactSchema()` fetches:
  - **ALL tables** from database
  - **ALL columns** for each table
  - Returns complete schema object

### Step 5: Build NLP Prompt
- `SQLGeneratorService::queryChatGPTForSQL()` builds prompt:
  - User's natural language query
  - Complete database schema (all tables, all columns)
  - Instructions for OpenAI

### Step 6: Send to OpenAI
- POST request to OpenAI API
- Model: `gpt-4` (better NLP understanding)
- Prompt includes:
  - Full user query
  - Complete database schema
  - Instructions to generate SQL

### Step 7: OpenAI NLP Processing
- OpenAI uses NLP to:
  - Understand the user's intent
  - Identify relevant tables from schema
  - Determine required columns
  - Generate appropriate SQL query

### Step 8: Receive SQL Query
- OpenAI returns generated SQL
- Example: `SELECT SUM(total_sales) as total FROM wp_wc_order_stats WHERE status = 'wc-completed'`

### Step 9: Validate SQL
- `MySQLService::executeSQLQuery()` validates:
  - Query is SELECT only (no INSERT/UPDATE/DELETE)
  - All referenced tables exist
  - SQL syntax is valid

### Step 10: Execute Locally
- SQL query executed on local database
- Results fetched from database

### Step 11: Return to Frontend
- Results formatted as JSON
- Sent back to frontend
- Displayed to user

## Key Features

### ✅ True NLP
- **No code-side filtering** of user input
- **No code-side table selection** - OpenAI decides
- **Full context** sent to AI for understanding

### ✅ Complete Schema
- All tables included
- All columns included
- OpenAI has full context to make decisions

### ✅ Security
- Only SELECT queries allowed
- Table name validation
- SQL injection protection via Laravel's query builder

### ✅ Transparency
- Generated SQL query included in response
- Full logging of each step
- Error messages are clear and helpful

## Files Involved

1. **Frontend**: `assets/js/chatbot.js`
   - Captures user input
   - Sends to API
   - Displays results

2. **Controller**: `api/app/Http/Controllers/NLPController.php`
   - Routes requests
   - Orchestrates the flow
   - Returns responses

3. **Schema Service**: `api/app/Services/MySQLService.php`
   - Fetches full database schema
   - Executes SQL queries
   - Validates queries

4. **NLP Service**: `api/app/Services/SQLGeneratorService.php`
   - Builds NLP prompts
   - Communicates with OpenAI
   - Processes SQL responses

## Example Flow

**User Input:**
```
"Can you give the overall sales data"
```

**Schema Sent to OpenAI:**
```
Table: wp_wc_order_stats
Columns: order_id, parent_id, date_created, total_sales, net_total, status, customer_id, ...

Table: wp_wc_order_product_lookup
Columns: order_item_id, order_id, product_id, product_gross_revenue, product_net_revenue, ...

... (all other tables)
```

**OpenAI Generates:**
```sql
SELECT 
    SUM(total_sales) as total_sales,
    SUM(net_total) as net_revenue,
    COUNT(*) as total_orders
FROM wp_wc_order_stats
WHERE status = 'wc-completed'
```

**Results:**
```json
{
  "success": true,
  "data": [
    {
      "total_sales": 50000.00,
      "net_revenue": 45000.00,
      "total_orders": 150
    }
  ],
  "sql_query": "SELECT SUM(total_sales)..."
}
```

## Benefits of This Approach

1. **True NLP**: AI understands natural language, not just keywords
2. **Flexible**: Can handle complex queries without code changes
3. **Accurate**: AI sees full schema, makes informed decisions
4. **Maintainable**: No hardcoded query logic
5. **Scalable**: Works with any database schema

## Logging

Each step is logged for debugging:
- `🔍 Starting NLP SQL Generation`
- `📝 User Query: ...`
- `📊 Schema: X tables`
- `🧠 Step 2: Sending to OpenAI...`
- `✅ Step 3: OpenAI generated SQL: ...`
- `💾 Step 4: Executing SQL query locally...`
- `✅ Step 5: Returning results...`

Check logs at: `api/storage/logs/laravel.log`

