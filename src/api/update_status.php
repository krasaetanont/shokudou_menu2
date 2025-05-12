if (select available from menu where id = $id) {
    update menu set available = false where id = $id;
}
else {
    update menu set available = true where id = $id;
}