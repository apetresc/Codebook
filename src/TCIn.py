#from xml.etree import ElementTree

import gdata.calendar.service
import gdata.service
import atom.service
import gdata.calendar
import atom
import getopt
import sys
import string
import time
import urllib2
from datetime import datetime, timedelta
from sgmllib import SGMLParser

urlopen = urllib2.urlopen

TOPCODER_EVENT_CALENDAR_BASE_URL = "http://www.topcoder.com/tc?module=Static&d1=calendar&d2="
DEFAULT_CALENDAR_NAME = "TopCoder"
DEFAULT_CALENDAR_DESC = "Contains a listing of various TopCoder competitions and events, most notably the weekly SRM matches."
DEFAULT_TOPCODER_URL = 'http://topcoder.com'
TIME_ZONE_OFFSET = -4

class TCEvent(object):
	def getDate(self):
		return 0
	def getName(self):
		return "TCEvent"

class SRMEvent(TCEvent):
	event_duration = 2
	id_num = 0
	year = ""
	month = ""
	day = ""
	start_time = ""
	sponsor = "None"
	prize = 0
	link = ""
	def getName(self):
		return "SRM " + str(self.id_num)
	def getStartTime(self):
		return self.start_time
	def getSponsor(self):
		return self.sponsor
	def getPrize(self):
		return self.prize
	def getLink(self):
		return self.link
	def getYear(self):
		return self.year
	def getMonth(self):
		return self.month
	def getDay(self):
		return self.day
	def getEventDuration(self):
		return SRMEvent.event_duration
	def __init__(self, id_num, year, month, day, start_time, link, sponsor="None", prize=0):
		self.id_num = id_num
		self.year = year
		self.month = month
		self.day = day
		self.start_time = start_time
		self.link = link
		self.sponsor = sponsor
		self.prize = prize
	def __str__(self):
		strep = ""
		strep += "Name: " + self.getName() + "\n"
		strep += "Date: " + str(self.getYear()) + ", " + str(self.getMonth()) + ", " + str(self.getDay()) + ", " + str(self.getStartTime()) + "\n"
		strep += "Link: " + self.getLink()+"\n"
		strep += "Prize: " + str(self.getPrize())+"\n\n"
		return strep
	
	def __repr__(self):
		return self.__str__()

class CalendarLink(object):
	cal_client = None
	tc_cal = None

	def __init__(self, email, password):
		self.cal_client = gdata.calendar.service.CalendarService()
		self.cal_client.email = email
		self.cal_client.password = password
		self.cal_client.source = 'TopCoder-Import-0.1'
		self.cal_client.ProgrammaticLogin()

	def checkExistingCalendar(self, name):
		feed = self.cal_client.GetOwnCalendarsFeed()
		for i, a_calendar in enumerate(feed.entry):
			if a_calendar.title.text == name:
				self.tc_cal = a_calendar
				#print a_calendar
				return True
		return False

	def addNewCalendar(self, name):
		newcal = gdata.calendar.CalendarListEntry()
		newcal.title = atom.Title(text=name)
		newcal.summary = atom.Summary(text=DEFAULT_CALENDAR_DESC)
		newcal.where = gdata.calendar.Where(value_string=DEFAULT_TOPCODER_URL)
		newcal.hidden = gdata.calendar.Hidden(value='false')
		newcal.timezone = gdata.calendar.Timezone(value='America/Toronto')
		newcal.selected = gdata.calendar.Selected(value='true')
		self.tc_cal = self.cal_client.InsertCalendar(new_calendar=newcal)

	def addTCEvent(self, tcevent):
		title = tcevent.getName() 
		content = "TopCoder Single Round Match. Have fun!\n"
		content += tcevent.getLink()
		if str(tcevent.getPrize()) != "0":
			content += "\nPrize: " + str(tcevent.getPrize())
		where = DEFAULT_TOPCODER_URL
		
		start_time = TC2date(tcevent)
		end_time = shiftHour(start_time, tcevent.getEventDuration())
		start_time = shiftHour(start_time, -TIME_ZONE_OFFSET)
		end_time = shiftHour(end_time, -TIME_ZONE_OFFSET)
		start_time = time.strftime('%Y-%m-%dT%H:%M:%S.000Z', start_time.timetuple())
		end_time = time.strftime('%Y-%m-%dT%H:%M:%S.000Z', end_time.timetuple())
		#print "Start: " + start_time + " End: " + end_time
		event = gdata.calendar.CalendarEventEntry()
		event.title = atom.Title(text=title)
		event.content = atom.Content(text=content)
		event.where.append(gdata.calendar.Where(value_string = where))
		event.when.append(gdata.calendar.When(start_time=start_time, end_time=end_time))
		#feed = '/calendar/feeds/default/private/full'
		#feed = '/calendar/feeds/5bko70ksltes1ge8mh6eatof8g%40group.calendar.google.com/private/full'
		feed = self.tc_cal.GetAlternateLink().href[21:]
		#print feed
		new_event = self.cal_client.InsertEvent(event, feed)

		print 'New single event inserted: %s' % (new_event.id.text,)
		#print '\tEvent edit URL: %s' % (new_event.GetEditLink().href,)
		#print '\tEvent HTML URL: %s' % (new_event.GetHtmlLink().href,)


class TCParser(SGMLParser):
	current_month = ""
	current_year = ""
	current_day = 0
	event_list = []
	handling_event = False
	accumulated_data = []

	def reset(self):
		SGMLParser.reset(self)
		self.current_month = None
		self.current_year = None
		self.current_day = 0
		self.event_list = []
		self.accumulated_data = []
	
	def add_event(self):
		year = self.current_year
		month = self.current_month
		day = self.current_day
		start_time = [x for x in self.accumulated_data if ':' in x][0]
		id_num = [x[4:] for x in self.accumulated_data if 'SRM' in x][0]
		prize = [x for x in self.accumulated_data if '$' in x]
		if len(prize) > 0:
			prize = prize[0]
		else:
			prize = 0
		link = [DEFAULT_TOPCODER_URL + x for x in self.accumulated_data if 'MatchDetails' in x][0]
		sponsor = "None"
		newevent = SRMEvent(id_num, year, month, day, start_time, link, sponsor, prize)
		self.event_list.append(newevent)

	def start_option(self, attrs):
		if ('selected', 'selected') in attrs:
			date = attrs[0][1]
			month_map = {'jan' : 'January',
				     'feb' : 'February',
				     'mar' : 'March',
				     'apr' : 'April',
				     'may' : 'May',
				     'jun' : 'June',
				     'jul' : 'July',
				     'aug' : 'August',
				     'sep' : 'September',
				     'oct' : 'October',
				     'nov' : 'November',
				     'dec' : 'December'}
			self.current_month = month_map[date[:3]]
			self.current_year = "20" + date[4:]

	def start_a(self, attrs):
		if self.handling_event:
			self.accumulated_data.append(attrs[0][1])

	def start_td(self, attrs):
		if ('class', 'value') in attrs:
			self.current_day = self.current_day + 1

	def start_div(self, attrs):
		if ('class', 'srm') in attrs:
			#print self.current_day
			self.handling_event = True
	
	def end_div(self):
		if self.handling_event:
			self.add_event()
			self.accumulated_data = []
			self.handling_event = False

	def handle_data(self, data):
		if self.handling_event:
			self.accumulated_data.append(data.strip())

def parseTCEventCalendar(link_url):
	tc_cal_conn = urlopen(link_url)
	tc_cal = tc_cal_conn.read()
	tc_cal_conn.close()
	parser = TCParser()
	parser.feed(tc_cal)
	parser.close()
	return parser.event_list

def TC2date(tc_event):
	tc_time = tc_event.getStartTime()
	month_map = {'January' : '01' ,
	     'February' : '02' ,
	     'March' : '03' ,
	     'April' : '04' ,
	     'May' : '05' ,
	     'June' : '06' ,
	     'July' : '07' ,
	     'August' : '08' ,
	     'September' : '09' ,
	     'October' : '10' ,
	     'November' : '11' ,
	     'December' : '12' }
	if "NOON" in tc_time or "Noon" in tc_time:
		dt = datetime(int(tc_event.getYear()),
				int(month_map[tc_event.getMonth()]),
				int(tc_event.getDay()),
				12,
				0,
				0,
				0,
				None)
	elif "MIDNIGHT" in tc_time or "Midnight" in tc_time:
		dt = datetime(int(tc_event.getYear()),
				int(month_map[tc_event.getMonth()]),
				int(tc_event.getDay()),
				0,
				0,
				0,
				0,
				None)
	elif "AM" in tc_time:
		dt = datetime(int(tc_event.getYear()),
			int(month_map[tc_event.getMonth()]),
			int(tc_event.getDay()),
			int(tc_event.getStartTime()[:len(tc_event.getStartTime())-6]),
			0,
			0,
			0,
			None)
	elif "PM" in tc_time:
		dt = datetime(int(tc_event.getYear()),
			int(month_map[tc_event.getMonth()]),
			int(tc_event.getDay()),
			int(tc_event.getStartTime()[:len(tc_event.getStartTime())-6]) + 12,
			00,
			00,
			00,
			None)
	return dt

def shiftHour(tc_time, offset):
	delta = timedelta(hours=offset)
	return tc_time + delta

def main():
	try:
		opts, args = getopt.getopt(sys.argv[1:], "", ["user=", "pw=", "calendar=", "month="])
	except getopt.error, msg:
		print ('python TCIn.py --user [username] --pw [password] --calendar [calendar name] --month [month_year]')
		sys.exit(2)

	user = ''
	pw = ''
	calendar_name = DEFAULT_CALENDAR_NAME
	month = "thisMonth"

	for o, a in opts:
		if o == "--user":
			user = a
		elif o == "--pw":
			pw = a
		elif o == "--calendar":
			calendar_name = a
		elif o == "--month":
			month = a
	if user == '' or pw == '':
		print ('python TCIn.py --user [username] --pw [password] --calendar [calendarname] --month [month_year]')
		sys.exit(2)
	print "Connecting to your Google Calendar account... "	,
	sys.stdout.flush()
	link = CalendarLink(user,pw)
	print "done!"
	print "Checking if calendar \"" + calendar_name + "\" exists... " ,
	sys.stdout.flush()
	calendar_exists = link.checkExistingCalendar(calendar_name)
	if not(calendar_exists):
		print "no!"
		print "Creating new calendar: \"" + calendar_name + "\"... " ,
		sys.stdout.flush()
		link.addNewCalendar(calendar_name)
		print "done!"
	else:
		print "yes!"
	print "Adding TopCoder events... "
	event_list = parseTCEventCalendar(TOPCODER_EVENT_CALENDAR_BASE_URL + month)
	if len(event_list) == 0:
		print "No events found!"
	else:
		for event in event_list:
			link.addTCEvent(event)
		print "Done!"

def test():
	list = parseTCEventCalendar(TOPCODER_EVENT_CALENDAR_BASE_URL + "jun_07")
	print list
	for event in list:
		print TC2date(event.getStartTime())

if __name__ == '__main__':
	#test()
	main()
