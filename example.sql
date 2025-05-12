-- Insert into menu table
INSERT INTO menu (name, price, available, available_date, tag)
VALUES 
    ('Cheeseburger', 8.99, TRUE, '2025-05-12', 'Fast Food'),
    ('Veggie Pizza', 10.50, TRUE, '2025-05-13', 'Vegetarian'),
    ('Miso Ramen', 9.25, FALSE, '2025-05-10', 'Japanese');

-- Insert into users table
INSERT INTO users (name, email, password)
VALUES 
    ('Alice Tanaka', 'alice@example.com', 'hashed_password_123'),
    ('Bob Sato', 'bob@example.com', 'hashed_password_456');

-- Insert into user_activities table
INSERT INTO user_activities (user_id, activity, activity_date)
VALUES 
    (1, 'Logged in', '2025-05-12'),
    (1, 'Viewed menu', '2025-05-12'),
    (2, 'Updated item', '2025-05-11');