CREATE TABLE IF NOT EXISTS menu (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price NUMERIC(10, 2) NOT NULL,
    available BOOLEAN DEFAULT TRUE,
    available_date DATE,
    tag VARCHAR(50)
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS user_activities (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id),
    activity TEXT,
    activity_date DATE
);
