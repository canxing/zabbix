/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

package com.zabbix.proxy;

import java.io.File;
import java.net.InetAddress;
import java.net.ServerSocket;
import java.util.concurrent.*;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class JavaProxy
{
	private static final Logger logger = LoggerFactory.getLogger(JavaProxy.class);

	public static void main(String[] args)
	{
		if (1 == args.length && (args[0].equals("-V") || args[0].equals("--version")))
		{
			GeneralInformation.printVersion();
			System.exit(0);
		}
		else if (0 != args.length)
		{
			System.out.println("unsupported command line options");
			System.exit(1);
		}

		logger.info("Zabbix Java Proxy {} (revision {}) has started", GeneralInformation.VERSION, GeneralInformation.REVISION);

		try
		{
			ConfigurationManager.parseConfiguration();

			InetAddress listenIP = (InetAddress)ConfigurationManager.getParameter(ConfigurationManager.LISTEN_IP).getValue();
			int listenPort = ConfigurationManager.getIntegerParameterValue(ConfigurationManager.LISTEN_PORT);

			ServerSocket socket = new ServerSocket(listenPort, 0, listenIP);
			socket.setReuseAddress(true);
			logger.info("listening on {}:{}", socket.getInetAddress(), socket.getLocalPort());

			int startPollers = ConfigurationManager.getIntegerParameterValue(ConfigurationManager.START_POLLERS);
			ExecutorService threadPool = new ThreadPoolExecutor(
					startPollers,
					startPollers,
					30L, TimeUnit.SECONDS,
					new ArrayBlockingQueue<Runnable>(startPollers),
					new ThreadPoolExecutor.CallerRunsPolicy());
			logger.debug("created a thread pool of {} pollers", startPollers);

			daemonize();

			while (true)
				threadPool.execute(new SocketProcessor(socket.accept()));
		}
		catch (Exception e)
		{
			logger.error("caught fatal exception", e);
		}
	}

	private static void daemonize() throws java.io.IOException
	{
		File pidFile = (File)ConfigurationManager.getParameter(ConfigurationManager.PID_FILE).getValue();

		if (null != pidFile)
		{
			pidFile.deleteOnExit();

			System.in.close();
			System.out.close();
			System.err.close();
		}

		Thread shutdownHook = new Thread()
		{
			public void run()
			{
				logger.info("Zabbix Java Proxy {} (revision {}) has stopped", GeneralInformation.VERSION, GeneralInformation.REVISION);
			}
		};

		Runtime.getRuntime().addShutdownHook(shutdownHook);
	}
}
