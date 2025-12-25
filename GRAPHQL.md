# GraphQL API - Service Extras

This document describes the GraphQL queries available for service extras.

## Table of Contents

- [Overview](#overview)
- [Queries](#queries)
- [Types](#types)
- [Usage Examples](#usage-examples)

## Overview

The Booked plugin provides GraphQL support for querying service extras and reservation extras. This allows you to build headless booking applications with modern frontend frameworks.

## Queries

### `serviceExtras`

Query all service extras or filter by service.

**Arguments:**
- `serviceId` (Int) - Optional. Filter extras by service ID
- `enabled` (Boolean) - Optional. Filter by enabled status (default: true)

**Returns:** `[ServiceExtra]`

### `serviceExtra`

Query a single service extra by ID.

**Arguments:**
- `id` (Int!) - Required. The ID of the service extra

**Returns:** `ServiceExtra`

## Types

### ServiceExtra

Represents a service extra/add-on.

```graphql
type ServiceExtra {
  id: Int!
  name: String!
  description: String
  price: Float!
  duration: Int!
  maxQuantity: Int!
  isRequired: Boolean!
  sortOrder: Int!
  enabled: Boolean!
  dateCreated: String
  dateUpdated: String
}
```

**Fields:**
- `id` - The unique identifier
- `name` - Display name (e.g., "Extended Session +30min")
- `description` - Optional description shown to customers
- `price` - Additional cost
- `duration` - Additional duration in minutes
- `maxQuantity` - Maximum quantity allowed per booking
- `isRequired` - Whether this extra is required
- `sortOrder` - Display order (lower numbers first)
- `enabled` - Whether this extra is currently available
- `dateCreated` - ISO 8601 datetime string
- `dateUpdated` - ISO 8601 datetime string

### ReservationExtra

Represents an extra selected for a specific reservation.

```graphql
type ReservationExtra {
  extra: ServiceExtra!
  quantity: Int!
  price: Float!
  totalPrice: Float!
}
```

**Fields:**
- `extra` - The service extra details
- `quantity` - Quantity selected
- `price` - Price at time of booking
- `totalPrice` - Total price (price × quantity)

### Reservation Extra Fields

When querying reservations, you can request these extra fields:

```graphql
type Reservation {
  # ... standard reservation fields ...

  # Extra fields (must be explicitly requested)
  extras: [ReservationExtra]
  extrasPrice: Float
  extrasSummary: String
  totalPrice: Float
  totalDuration: Int
  hasExtras: Boolean
}
```

## Usage Examples

### Query All Enabled Extras

```graphql
query {
  serviceExtras {
    id
    name
    description
    price
    duration
    maxQuantity
    isRequired
  }
}
```

### Query Extras for a Specific Service

```graphql
query {
  serviceExtras(serviceId: 5, enabled: true) {
    id
    name
    price
    duration
    isRequired
  }
}
```

### Query Single Extra by ID

```graphql
query {
  serviceExtra(id: 12) {
    id
    name
    description
    price
    duration
    maxQuantity
    isRequired
    enabled
    dateCreated
    dateUpdated
  }
}
```

### Query Reservation with Extras

```graphql
query {
  reservation(id: 123) {
    id
    userName
    userEmail
    bookingDate
    startTime
    endTime
    status

    # Request extras information
    extras {
      extra {
        id
        name
        price
      }
      quantity
      totalPrice
    }
    extrasPrice
    totalPrice
    totalDuration
    hasExtras
  }
}
```

### Frontend Booking Form Example

```javascript
// Fetch extras for a service
const query = `
  query GetServiceExtras($serviceId: Int!) {
    serviceExtras(serviceId: $serviceId, enabled: true) {
      id
      name
      description
      price
      duration
      maxQuantity
      isRequired
    }
  }
`;

fetch('/api', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    query,
    variables: { serviceId: 5 }
  })
})
.then(res => res.json())
.then(data => {
  const extras = data.data.serviceExtras;
  renderExtras(extras);
});
```

### Calculate Total Price

```javascript
// Query example to get pricing breakdown
const query = `
  query GetReservationPricing($reservationId: Int!) {
    reservation(id: $reservationId) {
      service {
        title
        price
      }
      quantity
      extras {
        extra {
          name
        }
        quantity
        totalPrice
      }
      extrasPrice
      totalPrice
    }
  }
`;

// Response will include:
// {
//   "service": { "title": "Massage", "price": 80 },
//   "quantity": 1,
//   "extras": [
//     { "extra": { "name": "Hot Stone" }, "quantity": 1, "totalPrice": 25 },
//     { "extra": { "name": "Extended Time" }, "quantity": 1, "totalPrice": 30 }
//   ],
//   "extrasPrice": 55,
//   "totalPrice": 135
// }
```

### React Hooks Example

```javascript
import { useQuery } from '@apollo/client';
import gql from 'graphql-tag';

const GET_SERVICE_EXTRAS = gql`
  query GetServiceExtras($serviceId: Int!) {
    serviceExtras(serviceId: $serviceId, enabled: true) {
      id
      name
      description
      price
      duration
      maxQuantity
      isRequired
    }
  }
`;

function ServiceExtrasSelector({ serviceId }) {
  const { loading, error, data } = useQuery(GET_SERVICE_EXTRAS, {
    variables: { serviceId }
  });

  if (loading) return <p>Loading extras...</p>;
  if (error) return <p>Error loading extras</p>;

  return (
    <div>
      <h3>Add Extras</h3>
      {data.serviceExtras.map(extra => (
        <div key={extra.id}>
          <label>
            <input
              type={extra.maxQuantity > 1 ? 'number' : 'checkbox'}
              max={extra.maxQuantity}
              required={extra.isRequired}
            />
            {extra.name} (+{extra.price} CHF)
            {extra.duration > 0 && ` (+${extra.duration} min)`}
          </label>
          {extra.description && <p>{extra.description}</p>}
        </div>
      ))}
    </div>
  );
}
```

## Best Practices

### 1. Request Only What You Need

Always request only the fields you actually use:

```graphql
# Good - minimal fields
query {
  serviceExtras(serviceId: 5) {
    id
    name
    price
  }
}

# Avoid - requesting unnecessary fields
query {
  serviceExtras(serviceId: 5) {
    id
    name
    description
    price
    duration
    maxQuantity
    isRequired
    sortOrder
    enabled
    dateCreated
    dateUpdated
  }
}
```

### 2. Use Variables for Dynamic Queries

```graphql
# Good - using variables
query GetExtras($serviceId: Int!) {
  serviceExtras(serviceId: $serviceId) {
    id
    name
  }
}

# Variables: { "serviceId": 5 }
```

### 3. Handle Required Extras

When building forms, make sure to enforce required extras:

```javascript
const requiredExtras = data.serviceExtras.filter(e => e.isRequired);
if (requiredExtras.length > 0) {
  // Show required badge, pre-select, and prevent deselection
}
```

### 4. Cache Extras Data

Since extras don't change frequently, cache the data:

```javascript
const { data } = useQuery(GET_SERVICE_EXTRAS, {
  variables: { serviceId },
  fetchPolicy: 'cache-first' // Use cached data if available
});
```

## Error Handling

GraphQL queries may return errors in these scenarios:

```json
{
  "errors": [
    {
      "message": "Service extra not found",
      "extensions": {
        "category": "graphql"
      }
    }
  ]
}
```

Always check for errors in your frontend:

```javascript
const { data, error } = await fetchGraphQL(query, variables);

if (error) {
  console.error('GraphQL error:', error);
  showErrorToUser('Failed to load extras');
  return;
}

if (!data?.serviceExtras) {
  console.warn('No extras found');
  return;
}
```

## Security

- GraphQL queries for service extras are public (no authentication required)
- Disabled extras are filtered out by default (use `enabled: false` to include them if needed)
- Admin-only operations (create, update, delete) are not available via GraphQL

## Support

For questions or issues with GraphQL:
- Check the [main documentation](README.md)
- Review [Service Extras Guide](SERVICE_EXTRAS_GUIDE.md)
- Search [GitHub Issues](https://github.com/fabian/booked/issues)
