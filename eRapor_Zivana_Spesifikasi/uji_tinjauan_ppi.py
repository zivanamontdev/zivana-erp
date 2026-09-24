import sqlite3
db=sqlite3.connect(':memory:'); c=db.cursor()
c.executescript("""
CREATE TABLE tahun_ajaran(id INTEGER PRIMARY KEY, nama TEXT, urutan INT);
CREATE TABLE periode(
  id INTEGER PRIMARY KEY, tahun_ajaran_id INT, urutan INT,
  semester TEXT, jenis TEXT, tanggal_mulai TEXT,
  UNIQUE(tahun_ajaran_id, urutan));
CREATE TABLE rapor(id INTEGER PRIMARY KEY, murid_id INT, tahun_ajaran_id INT,
  rubrik TEXT, semester TEXT, UNIQUE(murid_id,tahun_ajaran_id,rubrik,semester));
-- satu baris = satu rencana PPI yang sudah SELESAI
CREATE TABLE rencana(rapor_id INT, jenis TEXT, selesai INT, PRIMARY KEY(rapor_id,jenis));
CREATE TABLE ppi_capaian(rapor_id INT, jenis TEXT, horizon TEXT, pengisi TEXT,
  PRIMARY KEY(rapor_id,jenis,horizon,pengisi));
""")
for i,(n,u) in enumerate([("2025/2026",1),("2026/2027",2),("2027/2028",3)],1):
    c.execute("INSERT INTO tahun_ajaran VALUES(?,?,?)",(i,n,u))
slot=[(1,"GANJIL","TENGAH","10-01"),(2,"GANJIL","AKHIR","12-15"),
      (3,"GENAP","TENGAH","03-01"),(4,"GENAP","AKHIR","06-15")]
pid=0
for ta,(nm,_) in enumerate([("2025/2026",1),("2026/2027",2),("2027/2028",3)],1):
    y=int(nm[:4])
    for u,sem,jn,md in slot:
        pid+=1
        yy=y if u<=2 else y+1
        c.execute("INSERT INTO periode VALUES(?,?,?,?,?,?)",(pid,ta,u,sem,jn,f"{yy}-{md}"))

MURID=1
def rapor_id(ta,sem):
    c.execute("SELECT id FROM rapor WHERE murid_id=? AND tahun_ajaran_id=? AND rubrik='PPI' AND semester=?",(MURID,ta,sem))
    r=c.fetchone()
    if r: return r[0]
    c.execute("INSERT INTO rapor(murid_id,tahun_ajaran_id,rubrik,semester) VALUES(?,?,'PPI',?)",(MURID,ta,sem))
    return c.lastrowid

# murid punya rencana di semua periode KECUALI periode 3 (Tengah Genap 25/26) -> simulasi bolong
c.execute("SELECT id,tahun_ajaran_id,urutan,semester,jenis FROM periode ORDER BY id")
PER=c.fetchall()
for p in PER:
    if p[2]==3 and p[1]==1: continue           # bolong disengaja
    if p[0]>10: continue                        # sistem baru jalan sampai periode 10
    c.execute("INSERT INTO rencana VALUES(?,?,1)",(rapor_id(p[1],p[3]), p[4]))
db.commit()

Q = """
WITH ini AS (SELECT * FROM periode WHERE id=:p),
sebelumnya AS (                       -- jangka pendek: periode tepat sebelumnya
  SELECT p.* FROM periode p, ini
  WHERE p.tanggal_mulai < ini.tanggal_mulai
  ORDER BY p.tanggal_mulai DESC LIMIT 1),
setahun_lalu AS (                     -- jangka panjang: slot sama, tahun ajaran sebelumnya
  SELECT p.* FROM periode p, ini
  JOIN tahun_ajaran t_ini ON t_ini.id = ini.tahun_ajaran_id
  JOIN tahun_ajaran t_p   ON t_p.id   = p.tahun_ajaran_id
  WHERE p.urutan = ini.urutan AND t_p.urutan = t_ini.urutan - 1),
target AS (
  SELECT '3_BULAN' AS horizon, id,tahun_ajaran_id,urutan,semester,jenis FROM sebelumnya
  UNION ALL
  SELECT '1_TAHUN', id,tahun_ajaran_id,urutan,semester,jenis FROM setahun_lalu)
SELECT t.horizon, t.urutan, t.semester, t.jenis, r.id,
       (SELECT COUNT(*) FROM ppi_capaian x
         WHERE x.rapor_id=r.id AND x.jenis=t.jenis AND x.horizon=t.horizon)
FROM target t
JOIN rapor r ON r.murid_id=:m AND r.tahun_ajaran_id=t.tahun_ajaran_id
            AND r.rubrik='PPI' AND r.semester=t.semester
JOIN rencana rc ON rc.rapor_id=r.id AND rc.jenis=t.jenis AND rc.selesai=1
ORDER BY t.horizon DESC;
"""
LBL={1:"Tengah Ganjil",2:"Akhir Ganjil",3:"Tengah Genap",4:"Akhir Genap"}
print("Sesi di periode →  rencana yang perlu ditinjau\n"+"-"*62)
for p in PER:
    if p[0]>11: break
    c.execute(Q,{"p":p[0],"m":MURID})
    rows=c.fetchall()
    ta=["25/26","26/27","27/28"][p[1]-1]
    out=", ".join(f"{h.replace('_',' ').lower()} ← {LBL[u]}" for h,u,s,j,ri,n in rows) or "— tidak ada —"
    print(f"{ta} {LBL[p[2]]:<14} {out}")
