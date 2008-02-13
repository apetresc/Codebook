#****************************************************************
# Chrysler Theatre Schedule Import Script
#
# This script imports the events from the Chrysler Theatre event
# calendar into a given Google Calendar account, using the GData
# client library and BeautifulSoup for parsing.
#
# Author: Adrian Petrescu
# Date: February 2008
# License: GPLv2
#****************************************************************


import urllib2
import urllib
from BeautifulSoup import BeautifulSoup
import gdata.calendar.service
import gdata.service
import atom.service
import gdata.calendar
import atom
import getopt
import sys
import string
import time
from datetime import datetime, timedelta

urlopen = urllib2.urlopen

DEFAULT_CALENDAR_NAME = "Chrysler Theatre"
DEFAULT_CALENDAR_DESC = "Performances and concerts at the Chrysler Theatre in Windsor, Ontario"
DEFAULT_CHRYSLER_URL  = "https://secure1.tixhub.com/chryslertheatre/procurement/ShowCalendar.asp"
CHRYSLER_BASE_URL     = "https://secure1.tixhub.com/chryslertheatre/procurement/"

# Change this value if all of the events are off by some constant number of hours.
DEFAULT_TIME_OFFSET = 5 # Hours

class Event():
    pass

class CalendarLink(object):
    cal_client = None
    chrysler_cal = None

    def __init__(self, email, password):
        self.cal_client = gdata.calendar.service.CalendarService()
        self.cal_client.email = email
        self.cal_client.password = password
        self.cal_client.source = 'Chrysler-Import-0.1'
        self.cal_client.ProgrammaticLogin()

    def checkExistingCalendar(self, name):
        feed = self.cal_client.GetOwnCalendarsFeed()
        for i, a_calendar in enumerate(feed.entry):
	    if a_calendar.title.text == name:
	    	self.chrysler_cal = a_calendar
	    	#print a_calendar
	    	return True
	return False

    def addNewCalendar(self, name):
	newcal = gdata.calendar.CalendarListEntry()
	newcal.title = atom.Title(text=name)
	newcal.summary = atom.Summary(text=DEFAULT_CALENDAR_DESC)
        newcal.where = gdata.calendar.Where(value_string=DEFAULT_CHRYSLER_URL)
        newcal.hidden = gdata.calendar.Hidden(value='false')
        newcal.timezone = gdata.calendar.Timezone(value='America/Toronto')
        newcal.selected = gdata.calendar.Selected(value='true')
        self.chrysler_cal = self.cal_client.InsertCalendar(new_calendar=newcal)

    def removeCalendar(self, name):
        feed = self.cal_client.GetOwnCalendarsFeed()
        for entry in feed.entry:
                if entry.title.text == name:
                    self.cal_client.Delete(entry.GetEditLink().href)
                    

    def addEvent(self, event):
        title = event.name 
        try:
            content = getDescription(event.link)
        except Exception:
            pass
        where = event.place + ", Windsor, Ontario"

        start_time = [0, 0, 0, 0, 0, 0, 0, 0, -1]
        start_time[0] = int(event.date[string.rfind(event.date,'/')+1:])
        start_time[1] = int(event.date[:string.find(event.date,'/')])
        start_time[2] = int(event.date[string.find(event.date,'/')+1:string.rfind(event.date,'/')])
        start_time[3] = int(event.time[:string.find(event.time,':')])
        if (event.time[-2:] == "PM"):
            start_time[3] += 12
        start_time[4] = int(event.time[string.find(event.time,':')+1:-3])
        
        end_time = time.strftime('%Y-%m-%dT%H:%M:%S.000Z', time.localtime(time.mktime(tuple(start_time)) + 7200 + DEFAULT_TIME_OFFSET * 3600))
        start_time = time.strftime('%Y-%m-%dT%H:%M:%S.000Z', time.localtime(time.mktime(tuple(start_time)) + DEFAULT_TIME_OFFSET * 3600))

        cevent = gdata.calendar.CalendarEventEntry()
        cevent.title = atom.Title(text=title)
        cevent.content = atom.Content(text=content)
        cevent.where.append(gdata.calendar.Where(value_string=where))
        cevent.when.append(gdata.calendar.When(start_time=start_time, end_time=end_time))

        feed = self.chrysler_cal.GetAlternateLink().href[21:]
        new_event = self.cal_client.InsertEvent(cevent, feed)
        print "Added event" , title , "at" , event.time , event.date

def parseEventCalendar(link_url):
    event_list = []
    f = urllib.urlopen(link_url)
    s = f.read()
    f.close()
    soup = BeautifulSoup(s)
    allTags = soup.findAll(True)
    tds = [tag for tag in allTags if tag.name == 'td']
    tableEntries = []
    for td in tds:
        if td.has_key('class') and td['class'] == 'tableFields' and td.has_key('align') and td['align'] == 'center':
            tableEntries.append(td)
    numOfEvents = len(tableEntries)/5
    for i in range(numOfEvents):
        entry = tableEntries[i*5:(i+1)*5]
        event = Event()
        event.date = string.strip(entry[0].contents[0])
        event.time = string.strip(entry[1].contents[0])
        event.link = entry[2].a['href']
        event.name = string.strip(entry[2].a.contents[0])
        event.place = string.strip(entry[3].contents[0])
        event_list.append(event)
    return event_list

def getDescription(link_url):
    link_url = CHRYSLER_BASE_URL + link_url
    f = urllib.urlopen(link_url)
    s = f.read()
    f.close()
    soup = BeautifulSoup(s)
    tds = [string.strip(tag.contents[0]) for tag in soup.findAll(True) if tag.name == 'td' and tag.has_key('class') and tag['class'] == 'cmbStyle' and tag.has_key('align') and tag['align'] == 'left']  
    desc = ""
    for info in tds:
        desc += info + "\r\n"
    # Get rid of non-ASCII characters
    desc = string.replace(desc, u"\xe9", "e") # accent aigue
    return desc

    

def main():
        try:
                opts, args = getopt.getopt(sys.argv[1:], "", ["user=", "pw=", "calendar="])
        except getopt.error, msg:
                print ('python chrysler.py --user [username] --pw [password] --calendar [calendar name]')
                sys.exit(2)

        user = ''
        pw = ''
        calendar_name = DEFAULT_CALENDAR_NAME

        for o, a in opts:
                if o == "--user":
                        user = a
                elif o == "--pw":
                        pw = a
                elif o == "--calendar":
                        calendar_name = a
        if user == '' or pw == '':
                print ('python chrysler.py --user [username] --pw [password] --calendar [calendarname]')
                sys.exit(2)
        print "Connecting to your Google Calendar account... "  ,
        sys.stdout.flush()
        link = CalendarLink(user,pw)
        print "done!"
        print "Checking if calendar \"" + calendar_name + "\" exists... " ,
        sys.stdout.flush()
        calendar_exists = link.checkExistingCalendar(calendar_name)
        if calendar_exists:
                print "yes!"
                print "Deleting old calendar: \"" + calendar_name + "\"... " ,
                sys.stdout.flush()
                link.removeCalendar(calendar_name)
                print "done!"
        else:
                print "no!"
                
        print "Creating new calendar: \"" + calendar_name + "\"... " ,
        sys.stdout.flush()
        link.addNewCalendar(calendar_name)
        print "done!"

        print "Adding Chrysler Theatre events... "
        event_list = parseEventCalendar(DEFAULT_CHRYSLER_URL)
        for event in event_list:
            try:
                link.addEvent(event)
            except Exception:
                print "PROBLEM ADDING EVENT:" , event.name

        print "Done!"

if __name__ == '__main__':
        #test()
        main()
