# Pages
Pesanan terbuka (Open order)
Daftar pesanan (Order list)


# Modal
Buat butir pesanan
- Cari barang ...
- Barang ditemukan + Cari di TTCons...

Untuk mencari barang di sistem TTCons, masukkan ID akun groupware-mu
Mengontak sistem TTCons...

- Barang tidak ditemukan. Isi manual?

-- [a] Nama
-- [a] Deskripsi
-- [a] Kode
-- [a] Foto
-- [a] Harga satuan & curr
-- Keperluan
-- Qty & UoM
-- Amount (USD)
-- Asal (Domestik/Impor) Deteksi otomatis
-- Alias

# Authorization
(Empty) View
Buat butir pesanan


# Pesanan terbuka

Pesanan terbuka [v]

Cari ... | Pengguna | Keperluan                   TT MM | Tambah | ...

# Tables

inv_oitems
. name
. desc
. code
. photo
. unit_price
. inv_curr_id
. purpose

inv_oevals
. type // qty_decrease, qty_increase, 
. inv_oitem_id
. user_id
. message

inv_olists