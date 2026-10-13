from PIL import Image, ImageDraw, ImageFont
from pathlib import Path
import html

OUT = Path(__file__).parent
W,H = 2300,2300
im = Image.new('RGB',(W,H),'white')
d = ImageDraw.Draw(im)
fontpath = 'C:/Windows/Fonts/tahoma.ttf'
font = ImageFont.truetype(fontpath,20)
bold = ImageFont.truetype('C:/Windows/Fonts/tahomabd.ttf',24)
title = ImageFont.truetype('C:/Windows/Fonts/tahomabd.ttf',34)
svg = [f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">', '<rect width="100%" height="100%" fill="#ffffff"/>']
def text(x,y,s,f=font):
    s = {'เจ้าของ':'owns','มีรอบ':'has rounds','มีกิจกรรม':'has busy slots','สมาชิกของ':'has memberships','มีสมาชิก':'has members','เข้าร่วมรอบ':'joins rounds','มีผู้เข้าร่วม':'has participants','มีตัวเลือก':'has candidates','สำเนาเวลาไม่ว่าง':'has busy periods','ลงคะแนน':'casts vote','ได้รับคะแนน':'receives votes'}.get(s,s)
    d.text((x,y),s,font=f,fill='#000000')
    svg.append(f'<text x="{x}" y="{y+f.size}" font-family="Tahoma, sans-serif" font-size="{f.size}" fill="#000000">{html.escape(s)}</text>')
def line(points,width=2):
    d.line(points,fill='#000000',width=width)
    svg.append('<polyline points="'+' '.join(f'{x},{y}' for x,y in points)+f'" fill="none" stroke="#000000" stroke-width="{width}"/>')
def rect(x,y,w,h):
    d.rectangle((x,y,x+w,y+h),fill='white',outline='black',width=2)
    svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" fill="#ffffff" stroke="#000000" stroke-width="2"/>')
tables={
'users':(60,180,[
'id [PK] | รหัสผู้ใช้','name | ชื่อผู้ใช้','email [UK] | อีเมล','email_verified_at ? | เวลายืนยันอีเมล','password | รหัสผ่านที่แฮชแล้ว','two_factor_secret ? | ข้อมูลยืนยันสองขั้นตอน','two_factor_recovery_codes ? | รหัสกู้คืน','two_factor_confirmed_at ? | เวลายืนยัน 2FA','remember_token ? | โทเคนจดจำการเข้าสู่ระบบ','created_at, updated_at | เวลาสร้างและแก้ไข']),
'rooms':(840,180,[
'id [PK] | รหัสห้อง','owner_id [FK] | เจ้าของ → users.id','name | ชื่อห้อง','description ? | รายละเอียดห้อง','visibility | public / private','join_code [UK] | รหัสเข้าห้อง 12 ตัว','created_at, updated_at | เวลาสร้างและแก้ไข','deleted_at ? | เวลาลบแบบ soft delete']),
'meeting_rounds':(1620,180,[
'id [PK] | รหัสรอบนัดหมาย','room_id [FK] | ห้อง → rooms.id','round_no | ลำดับรอบภายในห้อง','status | active / completed / cancelled','phase | join / review / voting / final','active_marker ? | 1 เมื่อ active นอกนั้น NULL','search_start_date | วันแรกที่ค้นหาเวลานัด','search_end_date | วันสุดท้ายที่ค้นหาเวลานัด','daily_start_time | เวลาเริ่มค้นหาแต่ละวัน','daily_end_time | เวลาสิ้นสุดค้นหาแต่ละวัน','duration_minutes | ระยะเวลานัดเป็นนาที','professor_rule | at_least / all','min_professors ? | จำนวนอาจารย์ขั้นต่ำ','join_starts_at | เวลาเริ่มรับผู้เข้าร่วม','review_starts_at | เวลาเริ่มตรวจสอบตาราง','voting_starts_at | เวลาเริ่มโหวต','final_starts_at | เวลาเริ่มสรุปผล','cancelled_at ? | เวลายกเลิกรอบ','snapshot_taken_at ? | เวลาบันทึกสำเนาตาราง','candidates_generated_at ? | เวลาสร้างตัวเลือก','created_at, updated_at | เวลาสร้างและแก้ไข']),
'user_busy_slots':(60,850,[
'id [PK] | รหัสกิจกรรมส่วนตัว','user_id [FK] | เจ้าของ → users.id','name | ชื่อกิจกรรม','start_at | เวลาเริ่มไม่ว่าง','end_at | เวลาสิ้นสุดไม่ว่าง','created_at, updated_at | เวลาสร้างและแก้ไข','deleted_at ? | เวลาลบแบบ soft delete']),
'room_members':(840,850,[
'id [PK] | รหัสสมาชิกห้อง','room_id [FK] | ห้อง → rooms.id','user_id [FK] | ผู้ใช้ → users.id','role | student / professor','weight | น้ำหนักสมาชิก decimal(6,2)','status | active / left / removed','joined_at | เวลาเข้าห้อง','left_at ? | เวลาออกจากห้อง','created_at, updated_at | เวลาสร้างและแก้ไข']),
'round_candidates':(1620,1290,[
'id [PK] | รหัสตัวเลือกเวลานัด','meeting_round_id [FK] | → meeting_rounds.id','start_at | เวลาเริ่มนัดที่เสนอ','end_at | เวลาสิ้นสุดนัดที่เสนอ','available_count | จำนวนสมาชิกที่ว่าง','professor_count | จำนวนอาจารย์ที่ว่าง','available_member_ids | JSON รหัส round_members','is_winner | เป็นตัวเลือกที่ชนะหรือไม่']),
'round_members':(840,1540,[
'id [PK] | รหัสผู้เข้าร่วมรอบ','meeting_round_id [FK] | → meeting_rounds.id','room_member_id [FK] | → room_members.id','role | บทบาทที่ใช้ในรอบ','weight | น้ำหนักในรอบ decimal(6,2)','joined_at | เวลาเข้าร่วมรอบ','created_at, updated_at | เวลาสร้างและแก้ไข']),
'round_member_busy_periods':(60,1540,[
'id [PK] | รหัสช่วงเวลาไม่ว่างของรอบ','round_member_id [FK] | → round_members.id','start_at | เวลาเริ่มไม่ว่างที่คัดลอกไว้','end_at | เวลาสิ้นสุดไม่ว่างที่คัดลอกไว้']),
'round_votes':(1620,1860,[
'id [PK] | รหัสการลงคะแนน','round_member_id [FK, UK] | → round_members.id','round_candidate_id [FK] | → round_candidates.id','created_at, updated_at | เวลาสร้างและแก้ไข'])}
bw=620
def height(name): return 58+len(tables[name][2])*34
def edge(points,label,lx,ly,a='1',b='0..N'):
    line(points)
    text(lx,ly,label)
    def mark(endpoint,neighbor,many=False,optional=False):
        x,y=endpoint
        dx,dy=neighbor[0]-x,neighbor[1]-y
        size=(dx*dx+dy*dy)**0.5
        ux,uy=dx/size,dy/size
        vx,vy=-uy,ux
        def point(t,s=0): return (x+ux*t+vx*s,y+uy*t+vy*s)
        if many:
            line([point(0,-11),point(16),point(0,11)],3)
        else:
            line([point(9,-11),point(9,11)],3)
        if optional:
            cx,cy=point(27)
            d.ellipse((cx-7,cy-7,cx+7,cy+7),fill='white',outline='black',width=3)
            svg.append(f'<circle cx="{cx}" cy="{cy}" r="7" fill="#ffffff" stroke="#000000" stroke-width="3"/>')
        else:
            line([point(20,-11),point(20,11)],3)
    mark(points[0],points[1])
    mark(points[-1],points[-2],many=b=='0..N',optional=True)
text(60,30,'WAONGPA — ER DIAGRAM',title)
# Draw all connectors before entity boxes.
edge([(680,260),(840,260)],'เจ้าของ',700,218)
edge([(1460,260),(1620,260)],'มีรอบ',1480,218)
edge([(360,578),(360,850)],'มีกิจกรรม',375,705)
edge([(680,430),(755,430),(755,960),(840,960)],'สมาชิกของ',685,670)
edge([(1150,510),(1150,850)],'มีสมาชิก',1165,710)
edge([(1150,1214),(1150,1540)],'เข้าร่วมรอบ',1165,1390)
edge([(1620,900),(1510,900),(1510,1630),(1460,1630)],'มีผู้เข้าร่วม',1525,1200)
edge([(1930,952),(1930,1290)],'มีตัวเลือก',1945,1120)
edge([(840,1660),(680,1660)],'สำเนาเวลาไม่ว่าง',685,1610)
edge([(1460,1750),(1550,1750),(1550,1910),(1620,1910)],'ลงคะแนน',1465,1810,b='0..1')
edge([(1930,1620),(1930,1860)],'ได้รับคะแนน',1945,1740)
for name,(x,y,rows) in tables.items():
    rect(x,y,bw,height(name))
    text(x+16,y+12,name,bold)
    line([(x,y+52),(x+bw,y+52)])
    for i,row in enumerate(rows): text(x+16,y+62+i*34,row.split(' | ')[0].replace(' ?', ''))
svg.append('</svg>')
(OUT/'waongpa-er-crowfoot.svg').write_text('\n'.join(svg),encoding='utf-8')
im.save(OUT/'waongpa-er-crowfoot.png')
print(OUT/'waongpa-er-crowfoot.png')
