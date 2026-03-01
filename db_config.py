# db_config.py
import mysql.connector

conn=mysql.connector.connect(
        host="localhost",
        user="root",
        password="Nishka@2002",
        database="users"
    )
my_cursor=conn.cursor()


conn.commit()
conn.close()

print("connection succesfully created!")