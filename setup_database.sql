CREATE TABLE IF NOT EXISTS menu (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price INT NOT NULL,
    available BOOLEAN DEFAULT TRUE,
    available_date DATE,
    tag VARCHAR(50)
);